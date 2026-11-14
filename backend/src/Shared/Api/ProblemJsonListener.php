<?php

declare(strict_types=1);

namespace App\Shared\Api;

use App\Shared\Domain\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Renders every exception thrown under /api as RFC 7807 application/problem+json
 * with a stable `code` field the frontend can switch on.
 *
 * Runs after the security exception listener (priority 1) so access-denied errors
 * have already been converted to HTTP exceptions.
 */
#[AsEventListener(event: 'kernel.exception', priority: -8)]
final readonly class ProblemJsonListener
{
    private const array CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        412 => 'PRECONDITION_FAILED',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        422 => 'UNPROCESSABLE',
        429 => 'TOO_MANY_REQUESTS',
    ];

    private const array TITLES = [
        400 => 'Requête invalide.',
        401 => 'Authentification requise.',
        403 => 'Accès refusé.',
        404 => 'Ressource introuvable.',
        405 => 'Méthode non autorisée.',
        429 => 'Trop de requêtes, réessayez dans un instant.',
    ];

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $problem = $this->toProblem($throwable);
        $problem['instance'] = $request->getPathInfo();
        $status = $this->statusOf($throwable);

        if ($status >= 500) {
            $this->logger->error('Unhandled API exception', ['exception' => $throwable]);
        }

        $headers = ['Content-Type' => 'application/problem+json'];
        if ($throwable instanceof HttpExceptionInterface) {
            $headers = [...$throwable->getHeaders(), ...$headers];
        }

        $event->setResponse(new ProblemResponse($problem, $status, $headers));
    }

    public function statusOf(Throwable $e): int
    {
        return match (true) {
            $e instanceof DomainException => $e->httpStatus(),
            null !== $this->findValidationFailure($e) => 422,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toProblem(Throwable $e): array
    {
        if ($e instanceof DomainException) {
            return [
                ...$e->context(),
                'type' => 'about:blank',
                'title' => $e->getMessage(),
                'status' => $e->httpStatus(),
                'code' => $e->errorCode(),
            ];
        }

        $validation = $this->findValidationFailure($e);
        if (null !== $validation) {
            $violations = [];
            foreach ($validation->getViolations() as $violation) {
                $violations[] = [
                    'propertyPath' => $violation->getPropertyPath(),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            return [
                'type' => 'about:blank',
                'title' => 'Les données envoyées sont invalides.',
                'status' => 422,
                'code' => 'VALIDATION_FAILED',
                'violations' => $violations,
            ];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $status < 500 && '' !== $e->getMessage() ? $e->getMessage() : null;

            return [
                'type' => 'about:blank',
                'title' => $message ?? self::TITLES[$status] ?? 'Erreur.',
                'status' => $status,
                'code' => self::CODES[$status] ?? 'HTTP_'.$status,
            ];
        }

        $problem = [
            'type' => 'about:blank',
            'title' => 'Une erreur inattendue est survenue.',
            'status' => 500,
            'code' => 'INTERNAL_ERROR',
        ];
        if ($this->debug) {
            $problem['detail'] = $e->getMessage();
            $problem['exception'] = $e::class;
        }

        return $problem;
    }

    private function findValidationFailure(Throwable $e): ?ValidationFailedException
    {
        for ($cur = $e; null !== $cur; $cur = $cur->getPrevious()) {
            if ($cur instanceof ValidationFailedException) {
                return $cur;
            }
        }

        return null;
    }
}
