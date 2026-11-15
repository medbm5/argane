<?php

declare(strict_types=1);

namespace App\Reporting\Api;

use App\Reporting\Application\Query\SalesFilter;
use App\Reporting\Application\Query\SalesQueries;
use App\Reporting\Application\Query\View\SalesPageView;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class StudioSalesController
{
    private const array CHANNELS = ['ONLINE', 'BOX_OFFICE'];
    private const array STATUSES = ['PAID', 'CANCELLED', 'REFUNDED', 'PARTIALLY_REFUNDED'];

    public function __construct(private SalesQueries $sales)
    {
    }

    #[Route('/api/studio/sales', name: 'api_studio_sales', methods: ['GET'])]
    #[OA\Parameter(name: 'event', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'performance', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'channel', in: 'query', schema: new OA\Schema(type: 'string', enum: self::CHANNELS))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: self::STATUSES))]
    #[OA\Parameter(name: 'from', in: 'query', description: 'YYYY-MM-DD (venue day)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'YYYY-MM-DD inclusive', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Reference, name or email', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Orders, newest first, with totals', content: new Model(type: SalesPageView::class))]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->sales->page($this->filter($request)));
    }

    #[Route('/api/studio/sales.csv', name: 'api_studio_sales_csv', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One line per ticket (same filters), semicolon-separated UTF-8 with BOM for Excel', content: new OA\MediaType(mediaType: 'text/csv'))]
    public function csv(Request $request): StreamedResponse
    {
        $filter = $this->filter($request);
        $response = new StreamedResponse(function () use ($filter): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Référence', 'Commandé le', 'Spectacle', 'Représentation', 'Zone', 'Place', 'Tarif', 'Canal', 'Prix', 'Statut billet', 'Statut commande', 'Entré à', 'Client', 'Email'], ';', '"', '');
            foreach ($this->sales->ticketRows($filter) as $row) {
                fputcsv($out, [
                    $row['reference'],
                    self::local((string) $row['orderedAt']),
                    $row['event'],
                    self::local((string) $row['performance']),
                    $row['zone'],
                    $row['seat'] ?? 'debout',
                    $row['tariff'],
                    'ONLINE' === $row['channel'] ? 'en ligne' : 'guichet',
                    number_format(((int) $row['priceCents']) / 100, 2, ',', ''),
                    $row['ticketStatus'],
                    $row['orderStatus'],
                    null === $row['checkedInAt'] ? '' : self::local((string) $row['checkedInAt']),
                    $row['buyer'] ?? '',
                    $row['buyerEmail'] ?? '',
                ], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'ventes-argane.csv'));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function filter(Request $request): SalesFilter
    {
        $q = $request->query;
        $channel = $q->getString('channel') ?: null;
        $status = $q->getString('status') ?: null;
        if (null !== $channel && !\in_array($channel, self::CHANNELS, true) || null !== $status && !\in_array($status, self::STATUSES, true)) {
            throw BusinessRuleException::because('INVALID_FILTER', 'Filtre inconnu.', [], 400);
        }

        return new SalesFilter(
            $q->getString('event') ?: null,
            $q->getString('performance') ?: null,
            $channel,
            $status,
            self::day($q->getString('from'), false),
            self::day($q->getString('to'), true),
            $q->getString('q') ?: null,
            max(1, $q->getInt('page', 1)),
        );
    }

    /** A venue-local day bound, as UTC. `to` is inclusive, hence the next midnight. */
    private static function day(string $value, bool $end): ?DateTimeImmutable
    {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $start = VenueTime::fromVenueLocal($value.' 00:00:00');

        return $end ? $start->modify('+1 day') : $start;
    }

    private static function local(string $atom): string
    {
        return VenueTime::toVenue(new DateTimeImmutable($atom))->format('d/m/Y H:i');
    }
}
