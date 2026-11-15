<?php

declare(strict_types=1);

namespace App\Pricing\Domain;

use App\Pricing\Domain\Model\Tariff;
use App\Venue\Domain\Model\Venue;
use Symfony\Component\Uid\Uuid;

interface TariffRepository
{
    public function save(Tariff $tariff): void;

    public function find(Uuid $id): ?Tariff;

    public function findByCode(Venue $venue, string $code): ?Tariff;

    /**
     * @return list<Tariff>
     */
    public function ofVenue(Venue $venue, bool $activeOnly = true): array;
}
