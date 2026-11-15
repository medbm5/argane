<?php

declare(strict_types=1);

namespace App\Reporting\Api;

use App\Reporting\Application\AnalyticsService;
use App\Reporting\Application\Query\AnalyticsQueries;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * BI endpoints. Ranges are venue days (?from=YYYY-MM-DD&to=YYYY-MM-DD, `to` inclusive);
 * default: the last 30 days. Responses are private and short-lived.
 */
#[AsController]
#[OA\Tag(name: 'Analytics')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class AnalyticsController
{
    public function __construct(
        private AnalyticsService $analytics,
        private AnalyticsQueries $queries,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/studio/analytics/overview', name: 'api_analytics_overview', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'KPIs (revenue, tickets, orders, average basket, fill rate, online share, refunds) with previous-period deltas, and the daily series')]
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return self::json($this->analytics->overview($from, $to));
    }

    #[Route('/api/studio/analytics/fill', name: 'api_analytics_fill', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Fill per performance and zone (performances starting in the range)')]
    public function fill(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request, futureDefault: true);

        return self::json($this->queries->fill($from, $to));
    }

    #[Route('/api/studio/analytics/revenue', name: 'api_analytics_revenue', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Net revenue by tariff and by channel')]
    public function revenue(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return self::json($this->queries->revenueBreakdown($from, $to));
    }

    #[Route('/api/studio/analytics/timeseries', name: 'api_analytics_timeseries', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Tickets and revenue per venue day')]
    public function timeseries(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return self::json($this->queries->daily($from, $to));
    }

    #[Route('/api/studio/analytics/booking-curve', name: 'api_analytics_booking_curve', methods: ['GET'])]
    #[OA\Parameter(name: 'performance', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Cumulative share sold N days before the show: past average and, optionally, one performance')]
    public function bookingCurve(Request $request): JsonResponse
    {
        return self::json($this->queries->bookingCurve($this->clock->now(), $request->query->getString('performance') ?: null));
    }

    #[Route('/api/studio/analytics/heatmap', name: 'api_analytics_heatmap', methods: ['GET'])]
    #[OA\Parameter(name: 'layout', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Sell-through rate per seat of a layout')]
    public function heatmap(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request, wide: true);

        return self::json($this->queries->seatHeatmap($request->query->getString('layout'), $from, $to));
    }

    #[Route('/api/studio/analytics/at-risk', name: 'api_analytics_at_risk', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Upcoming performances selling slower than past ones at the same distance from the show')]
    public function atRisk(): JsonResponse
    {
        return self::json($this->queries->atRisk($this->clock->now()));
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function range(Request $request, bool $futureDefault = false, bool $wide = false): array
    {
        $from = $request->query->getString('from');
        $to = $request->query->getString('to');
        $day = '/^\d{4}-\d{2}-\d{2}$/';
        if ('' === $from && '' === $to) {
            $now = $this->clock->now();

            return match (true) {
                $wide => [$now->modify('-1 year'), $now->modify('+1 year')],
                $futureDefault => [$now->modify('-30 days'), $now->modify('+90 days')],
                default => $this->analytics->defaultRange(),
            };
        }
        if (1 !== preg_match($day, $from) || 1 !== preg_match($day, $to) || $from > $to) {
            throw BusinessRuleException::because('INVALID_RANGE', 'Période invalide (AAAA-MM-JJ, début avant fin).', [], 400);
        }

        return [VenueTime::fromVenueLocal($from.' 00:00:00'), VenueTime::fromVenueLocal($to.' 00:00:00')->modify('+1 day')];
    }

    private static function json(mixed $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->setPrivate();
        $response->setMaxAge(30);

        return $response;
    }
}
