<?php

declare(strict_types=1);

namespace App\Ticketing\Api;

use App\Booking\Application\Dto\OrderView;
use App\Identity\Domain\Model\User;
use App\Ticketing\Application\Dto\Printable;
use App\Ticketing\Application\TicketService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Tickets')]
#[IsGranted('ROLE_CUSTOMER')]
final readonly class TicketController
{
    public function __construct(
        private TicketService $tickets,
        private Security $security,
    ) {
    }

    #[Route('/api/me/orders', name: 'api_wallet', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The caller\'s ticketed orders, soonest first, with QR payloads for live tickets', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: OrderView::class))))]
    public function wallet(#[CurrentUser] User $user): JsonResponse
    {
        $response = new JsonResponse($this->tickets->wallet($user->id()));
        $response->setPrivate();

        return $response;
    }

    #[Route('/api/orders/{id}/tickets.pdf', name: 'api_order_pdf', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All live tickets of the order, one page each', content: new OA\MediaType(mediaType: 'application/pdf'))]
    public function orderPdf(string $id, #[CurrentUser] User $user): Response
    {
        return self::pdf($this->tickets->orderPdf($id, $user->id(), $this->security->isGranted('ROLE_BOX_OFFICE')));
    }

    #[Route('/api/tickets/{id}.pdf', name: 'api_ticket_pdf', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One ticket', content: new OA\MediaType(mediaType: 'application/pdf'))]
    public function ticketPdf(string $id, #[CurrentUser] User $user): Response
    {
        return self::pdf($this->tickets->ticketPdf($id, $user->id(), $this->security->isGranted('ROLE_BOX_OFFICE')));
    }

    private static function pdf(Printable $printable): Response
    {
        return new Response($printable->bytes, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $printable->filename),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
