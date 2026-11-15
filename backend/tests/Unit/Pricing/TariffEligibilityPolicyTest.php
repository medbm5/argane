<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing;

use App\Pricing\Application\TariffEligibilityPolicy;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;
use App\Tests\Support\CatalogFactory;
use App\Venue\Domain\Model\Venue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TariffEligibilityPolicyTest extends TestCase
{
    private const array CUSTOMER = ['ROLE_CUSTOMER'];
    private const array BOX_OFFICE = ['ROLE_BOX_OFFICE', 'ROLE_CUSTOMER'];
    private const array ORGANIZER = ['ROLE_ORGANIZER', 'ROLE_BOX_OFFICE', 'ROLE_CUSTOMER'];

    private static ?Venue $venue = null;

    /**
     * The README matrix, expressed as tariff data.
     */
    private static function tariff(string $code): Tariff
    {
        self::$venue ??= CatalogFactory::venue();
        $both = [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE];

        return match ($code) {
            'FULL' => new Tariff(self::$venue, 'FULL', 'Plein', $both),
            'REDUCED' => new Tariff(self::$venue, 'REDUCED', 'Réduit', $both, requiresProofAtEntry: true),
            'GROUP' => new Tariff(self::$venue, 'GROUP', 'Groupe', $both, minQuantity: 10, maxQuantity: 30),
            'INVITATION' => new Tariff(self::$venue, 'INVITATION', 'Invitation', [SalesChannel::BOX_OFFICE], ['ROLE_BOX_OFFICE'], isFree: true),
            default => throw new LogicException($code),
        };
    }

    /**
     * @return iterable<string, array{string, SalesChannel, list<string>, int, ?string, ?string}>
     *                                                                                            tariff, channel, roles, quantity, reason, expected error code (null = allowed)
     */
    public static function matrix(): iterable
    {
        foreach (['FULL', 'REDUCED'] as $code) {
            yield "$code online customer" => [$code, SalesChannel::ONLINE, self::CUSTOMER, 2, null, null];
            yield "$code box office" => [$code, SalesChannel::BOX_OFFICE, self::BOX_OFFICE, 2, null, null];
            yield "$code organizer" => [$code, SalesChannel::BOX_OFFICE, self::ORGANIZER, 2, null, null];
        }

        yield 'GROUP online at 10' => ['GROUP', SalesChannel::ONLINE, self::CUSTOMER, 10, null, null];
        yield 'GROUP online at 30' => ['GROUP', SalesChannel::ONLINE, self::CUSTOMER, 30, null, null];
        yield 'GROUP online below min' => ['GROUP', SalesChannel::ONLINE, self::CUSTOMER, 9, null, 'TARIFF_MIN_QUANTITY'];
        yield 'GROUP box office above max' => ['GROUP', SalesChannel::BOX_OFFICE, self::BOX_OFFICE, 31, null, 'TARIFF_MAX_QUANTITY'];
        yield 'GROUP box office at 12' => ['GROUP', SalesChannel::BOX_OFFICE, self::BOX_OFFICE, 12, null, null];

        yield 'INVITATION online' => ['INVITATION', SalesChannel::ONLINE, self::CUSTOMER, 1, 'Presse', 'TARIFF_CHANNEL_NOT_ALLOWED'];
        yield 'INVITATION online even for organizer' => ['INVITATION', SalesChannel::ONLINE, self::ORGANIZER, 1, 'Presse', 'TARIFF_CHANNEL_NOT_ALLOWED'];
        yield 'INVITATION box office with reason' => ['INVITATION', SalesChannel::BOX_OFFICE, self::BOX_OFFICE, 2, 'Presse locale', null];
        yield 'INVITATION box office without reason' => ['INVITATION', SalesChannel::BOX_OFFICE, self::BOX_OFFICE, 2, '  ', 'INVITATION_REASON_REQUIRED'];
        yield 'INVITATION organizer with reason' => ['INVITATION', SalesChannel::BOX_OFFICE, self::ORGANIZER, 4, 'Partenaire', null];
        yield 'INVITATION by a customer at the counter' => ['INVITATION', SalesChannel::BOX_OFFICE, self::CUSTOMER, 1, 'x', 'TARIFF_ROLE_NOT_ALLOWED'];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('matrix')]
    public function testAppliesTheMatrix(string $code, SalesChannel $channel, array $roles, int $quantity, ?string $reason, ?string $expected): void
    {
        $policy = new TariffEligibilityPolicy();

        try {
            $policy->assertEligible(self::tariff($code), $channel, $roles, $quantity, $reason);
            self::assertNull($expected, sprintf('Expected %s', $expected));
        } catch (BusinessRuleException $e) {
            self::assertSame($expected, $e->errorCode(), $e->getMessage());
        }
    }

    public function testInactiveTariffsAreRefused(): void
    {
        $tariff = self::tariff('FULL');
        $tariff->activate(false);

        $this->expectExceptionObject(BusinessRuleException::because('TARIFF_INACTIVE', 'Le tarif Plein n’est plus proposé.'));
        (new TariffEligibilityPolicy())->assertEligible($tariff, SalesChannel::ONLINE, self::CUSTOMER, 1);
    }

    public function testOffersOnlyWhatTheSellerMaySell(): void
    {
        $all = array_map(self::tariff(...), ['FULL', 'REDUCED', 'GROUP', 'INVITATION']);
        $codes = static fn (array $tariffs): array => array_map(static fn (Tariff $t): string => $t->code(), $tariffs);
        $policy = new TariffEligibilityPolicy();

        self::assertSame(['FULL', 'REDUCED', 'GROUP'], $codes($policy->offerable($all, SalesChannel::ONLINE, self::ORGANIZER)));
        self::assertSame(['FULL', 'REDUCED', 'GROUP', 'INVITATION'], $codes($policy->offerable($all, SalesChannel::BOX_OFFICE, self::BOX_OFFICE)));
        self::assertSame(['FULL', 'REDUCED', 'GROUP'], $codes($policy->offerable($all, SalesChannel::BOX_OFFICE, self::CUSTOMER)));
    }

    public function testTariffsValidateTheirOwnData(): void
    {
        $venue = CatalogFactory::venue();

        try {
            new Tariff($venue, 'X', 'X', []);
            self::fail('Expected an exception');
        } catch (BusinessRuleException $e) {
            self::assertSame('TARIFF_NO_CHANNEL', $e->errorCode());
        }

        $this->expectExceptionObject(BusinessRuleException::because('TARIFF_INVALID_QUANTITY', 'Quantités minimum et maximum incohérentes.'));
        new Tariff($venue, 'X', 'X', [SalesChannel::ONLINE], minQuantity: 10, maxQuantity: 5);
    }
}
