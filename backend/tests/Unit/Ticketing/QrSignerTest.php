<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ticketing;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\Ticket;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;
use App\Tests\Support\CatalogFactory;
use App\Ticketing\Application\QrSigner;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class QrSignerTest extends TestCase
{
    private function ticket(): Ticket
    {
        $venue = CatalogFactory::venue();
        $layout = CatalogFactory::layout($venue);
        $performance = CatalogFactory::performance(CatalogFactory::event($venue), $layout);
        $order = new Order('ARG-26-000001', $performance, SalesChannel::ONLINE, 'MAD', new DateTimeImmutable());
        $zone = $layout->zoneByCode('FOSSE') ?? self::fail();

        return new Ticket($order, $performance, $zone, new Tariff($venue, 'FULL', 'Plein', [SalesChannel::ONLINE]), 9000);
    }

    public function testSignsAndRecognisesItsOwnPayload(): void
    {
        $signer = new QrSigner('s3cret');
        $ticket = $this->ticket();
        $payload = $signer->sign($ticket);

        self::assertMatchesRegularExpression('/^ARG1\.[1-9A-HJ-NP-Za-km-z]{22}\.[A-Za-z0-9_-]{22}$/', $payload);
        self::assertTrue($ticket->id()->equals($signer->ticketId($payload)));
        self::assertTrue($signer->isAuthentic($payload, $ticket));
    }

    public function testRejectsTamperingAnotherSecretAndRotatedNonces(): void
    {
        $ticket = $this->ticket();
        $payload = (new QrSigner('s3cret'))->sign($ticket);

        $tampered = substr($payload, 0, -1).('A' === substr($payload, -1) ? 'B' : 'A');
        self::assertFalse((new QrSigner('s3cret'))->isAuthentic($tampered, $ticket));
        self::assertFalse((new QrSigner('other'))->isAuthentic($payload, $ticket));

        (new ReflectionProperty(Ticket::class, 'qrNonce'))->setValue($ticket, bin2hex(random_bytes(16)));
        self::assertFalse((new QrSigner('s3cret'))->isAuthentic($payload, $ticket), 're-issued ticket invalidates the old QR');
    }

    public function testRefusesForeignCodes(): void
    {
        $this->expectException(BusinessRuleException::class);
        (new QrSigner('s3cret'))->ticketId('https://example.com/not-a-ticket');
    }
}
