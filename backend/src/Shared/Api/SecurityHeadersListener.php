<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Hardening headers on every API response. The API only serves JSON, PDFs and CSV,
 * so the content policy forbids everything; the Swagger UI page is left alone.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if (!$headers->has('Cross-Origin-Resource-Policy')) {
            // Responses meant for other sites (poster images) set their own.
            $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        }
        if (!str_starts_with($request->getPathInfo(), '/api/doc')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
