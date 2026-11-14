<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Api\ProblemJsonListener;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final class ProblemJsonListenerTest extends TestCase
{
    public function testDomainExceptionKeepsCodeStatusAndContext(): void
    {
        $response = $this->handle('/api/holds', ConflictException::because(
            'SEAT_UNAVAILABLE',
            'Ces places viennent d\'être réservées.',
            ['unavailableSeatIds' => ['a', 'b']],
        ));

        self::assertNotNull($response);
        self::assertSame(409, $response['status']);
        self::assertSame('SEAT_UNAVAILABLE', $response['code']);
        self::assertSame(['a', 'b'], $response['unavailableSeatIds']);
        self::assertSame('/api/holds', $response['instance']);
    }

    public function testNotFound(): void
    {
        $response = $this->handle('/api/events/x', NotFoundException::of('Spectacle', 'x'));

        self::assertNotNull($response);
        self::assertSame(404, $response['status']);
        self::assertSame('NOT_FOUND', $response['code']);
    }

    public function testValidationFailureListsViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Cette valeur ne doit pas être vide.', null, [], null, 'seatIds', null),
        ]);
        $exception = new UnprocessableEntityHttpException('', new ValidationFailedException(null, $violations));

        $response = $this->handle('/api/holds', $exception);

        self::assertNotNull($response);
        self::assertSame('VALIDATION_FAILED', $response['code']);
        self::assertSame('seatIds', $response['violations'][0]['propertyPath']);
    }

    public function testHttpExceptionMapsToStableCode(): void
    {
        $response = $this->handle('/api/studio/events', new AccessDeniedHttpException());

        self::assertNotNull($response);
        self::assertSame(403, $response['status']);
        self::assertSame('FORBIDDEN', $response['code']);
    }

    public function testUnexpectedErrorsHideDetailsOutsideDebug(): void
    {
        $response = $this->handle('/api/health', new RuntimeException('SQLSTATE secret'));

        self::assertNotNull($response);
        self::assertSame('INTERNAL_ERROR', $response['code']);
        self::assertArrayNotHasKey('detail', $response);
    }

    public function testIgnoresNonApiPaths(): void
    {
        self::assertNull($this->handle('/', new RuntimeException('x')));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function handle(string $path, Throwable $e): ?array
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST, $e);

        (new ProblemJsonListener(new NullLogger(), false))($event);

        $response = $event->getResponse();
        if (null === $response) {
            return null;
        }
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
