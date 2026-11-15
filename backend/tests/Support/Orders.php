<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Booking\Application\CheckoutService;
use App\Booking\Application\Dto\CheckoutRequest;
use App\Booking\Application\Dto\HoldRequest;
use App\Booking\Application\HoldService;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\OrderRepository;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\User;
use App\Payment\Application\PaymentGateway;
use App\Pricing\Domain\Model\Tariff;
use App\Pricing\Domain\TariffRepository;
use Doctrine\DBAL\Connection;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Uid\Uuid;

/** Drives the real hold → checkout → payment services to produce a paid order. */
final class Orders
{
    /**
     * @param list<string> $seatLabels
     */
    public static function paid(ContainerInterface $container, Performance $performance, User $buyer, array $seatLabels = ['A-1'], int $standing = 0, string $tariffCode = Tariff::FULL): Order
    {
        /** @var HoldService $holds */
        $holds = $container->get(HoldService::class); // @phpstan-ignore symfonyContainer.privateService (test container)
        /** @var CheckoutService $checkout */
        $checkout = $container->get(CheckoutService::class); // @phpstan-ignore symfonyContainer.privateService (test container)
        /** @var TariffRepository $tariffs */
        $tariffs = $container->get(TariffRepository::class); // @phpstan-ignore symfonyContainer.privateService (test container)
        /** @var Connection $db */
        $db = $container->get(Connection::class); // @phpstan-ignore symfonyContainer.privateService (test container)
        /** @var OrderRepository $orders */
        $orders = $container->get(OrderRepository::class); // @phpstan-ignore symfonyContainer.privateService (test container)
        /** @var PaymentGateway $gateway */
        $gateway = $container->get(PaymentGateway::class); // @phpstan-ignore symfonyContainer.privateService (test container)

        $seats = SalesFactory::seatIds($db, $performance);
        $fosse = SalesFactory::zoneId($performance, 'FOSSE');
        $tariff = $tariffs->findByCode($performance->event()->venue(), $tariffCode) ?? throw new LogicException($tariffCode);
        $seatIds = array_map(static fn (string $l): string => $seats[$l], $seatLabels);

        $hold = $holds->hold($performance->id()->toRfc4122(), $buyer->id(), ['ROLE_CUSTOMER'], new HoldRequest(
            seatIds: $seatIds,
            standing: $standing > 0 ? [['zoneId' => $fosse, 'quantity' => $standing]] : [],
        ));
        $view = $checkout->checkout($hold->id, $buyer->id(), $buyer->email(), ['ROLE_CUSTOMER'], new CheckoutRequest(
            seats: array_map(static fn (string $id): array => ['seatId' => $id, 'tariffId' => $tariff->id()->toRfc4122()], $seatIds),
            standing: $standing > 0 ? [['zoneId' => $fosse, 'tariffId' => $tariff->id()->toRfc4122(), 'quantity' => $standing]] : [],
        ));

        if (!$gateway instanceof FakePaymentGateway) {
            throw new LogicException('Tests must use the fake gateway.');
        }
        $intent = array_key_last($gateway->intents) ?? throw new LogicException('No intent created');
        $checkout->paymentSucceeded((string) $intent);

        return $orders->find(Uuid::fromString($view->order->id)) ?? throw new LogicException('Order vanished');
    }
}
