<?php

declare(strict_types=1);

namespace App\Pricing\Api;

use App\Pricing\Application\Dto\PriceGridView;
use App\Pricing\Application\Dto\TariffInput;
use App\Pricing\Application\Dto\TariffView;
use App\Pricing\Application\PricingStudio;
use App\Shared\Domain\Exception\BusinessRuleException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class StudioPricingController
{
    private const string UUID = '[0-9a-fA-F-]{36}';

    public function __construct(private PricingStudio $studio)
    {
    }

    #[Route('/api/studio/tariffs', name: 'api_studio_tariffs', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All tariffs, inactive included', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: TariffView::class))))]
    public function tariffs(): JsonResponse
    {
        return new JsonResponse($this->studio->tariffs());
    }

    #[Route('/api/studio/tariffs', name: 'api_studio_tariff_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: TariffInput::class))]
    #[OA\Response(response: 201, description: 'Tariff created', content: new Model(type: TariffView::class))]
    public function create(#[MapRequestPayload] TariffInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->createTariff($input), Response::HTTP_CREATED);
    }

    #[Route('/api/studio/tariffs/{id}', name: 'api_studio_tariff_update', requirements: ['id' => self::UUID], methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: TariffInput::class))]
    #[OA\Response(response: 200, description: 'Tariff updated (code is permanent)', content: new Model(type: TariffView::class))]
    public function update(string $id, #[MapRequestPayload] TariffInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->updateTariff($id, $input));
    }

    #[Route('/api/studio/tariffs/{id}/{state}', name: 'api_studio_tariff_activate', requirements: ['id' => self::UUID, 'state' => 'activate|deactivate'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Active state changed', content: new Model(type: TariffView::class))]
    public function activate(string $id, string $state): JsonResponse
    {
        return new JsonResponse($this->studio->activate($id, 'activate' === $state));
    }

    #[Route('/api/studio/performances/{id}/prices', name: 'api_studio_prices', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Zones × tariffs grid', content: new Model(type: PriceGridView::class))]
    public function grid(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->grid($id));
    }

    #[Route('/api/studio/performances/{id}/prices', name: 'api_studio_prices_update', requirements: ['id' => self::UUID], methods: ['PUT'])]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'cells', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'zoneId', type: 'string'), new OA\Property(property: 'tariffId', type: 'string'), new OA\Property(property: 'amountCents', type: 'integer', nullable: true)]))]))]
    #[OA\Response(response: 200, description: 'Grid replaced (null cells removed); sold tickets keep their price', content: new Model(type: PriceGridView::class))]
    public function setGrid(string $id, Request $request): JsonResponse
    {
        $cells = $request->toArray()['cells'] ?? null;
        if (!\is_array($cells)) {
            throw BusinessRuleException::because('INVALID_GRID', 'Grille de prix invalide.');
        }
        $clean = [];
        foreach ($cells as $cell) {
            if (!\is_array($cell) || !\is_string($cell['zoneId'] ?? null) || !\is_string($cell['tariffId'] ?? null)) {
                throw BusinessRuleException::because('INVALID_GRID', 'Grille de prix invalide.');
            }
            $amount = $cell['amountCents'] ?? null;
            if (null !== $amount && (!\is_int($amount) || $amount < 0 || $amount > 10_000_000)) {
                throw BusinessRuleException::because('INVALID_PRICE', 'Prix invalide.', ['zoneId' => $cell['zoneId'], 'tariffId' => $cell['tariffId']]);
            }
            $clean[] = ['zoneId' => $cell['zoneId'], 'tariffId' => $cell['tariffId'], 'amountCents' => $amount];
        }

        return new JsonResponse($this->studio->setGrid($id, $clean));
    }

    #[Route('/api/studio/performances/{id}/prices/apply-to-event', name: 'api_studio_prices_apply', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Grid copied to the other performances of the event', content: new OA\JsonContent(properties: [new OA\Property(property: 'updated', type: 'integer')]))]
    public function applyToEvent(string $id): JsonResponse
    {
        return new JsonResponse(['updated' => $this->studio->applyToEvent($id)]);
    }
}
