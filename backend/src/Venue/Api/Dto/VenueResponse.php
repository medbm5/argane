<?php

declare(strict_types=1);

namespace App\Venue\Api\Dto;

use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Venue;

final readonly class VenueResponse
{
    /**
     * @param list<LayoutSummary> $layouts
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $city,
        public string $address,
        public string $timezone,
        public string $currency,
        public string $tagline,
        public int $since,
        public int $capacity,
        public array $layouts,
    ) {
    }

    /**
     * @param list<HallLayout> $layouts
     */
    public static function from(Venue $venue, array $layouts, string $currency): self
    {
        $summaries = array_map(
            static fn (HallLayout $l): LayoutSummary => new LayoutSummary($l->id()->toRfc4122(), $l->name(), $l->capacity(), $l->isDefault()),
            $layouts,
        );
        $default = array_values(array_filter($summaries, static fn (LayoutSummary $l): bool => $l->isDefault))[0] ?? $summaries[0] ?? null;

        return new self(
            $venue->name(),
            $venue->slug(),
            $venue->city(),
            $venue->address(),
            $venue->timezone(),
            $currency,
            'Chaque place raconte une histoire.',
            1974,
            null === $default ? 0 : $default->capacity,
            $summaries,
        );
    }
}
