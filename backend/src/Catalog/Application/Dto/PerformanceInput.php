<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Studio scheduling form. Times are venue-local wall-clock ("2026-12-12T20:00"), as the
 * organiser thinks of them; they are converted to UTC on the way in.
 */
final class PerformanceInput
{
    private const string LOCAL = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(self::LOCAL)]
        public string $startsAt = '',
        #[Assert\NotBlank]
        #[Assert\Regex(self::LOCAL)]
        public string $salesOpenAt = '',
        /** Default: at curtain time. */
        #[Assert\Regex(self::LOCAL)]
        public ?string $salesCloseAt = null,
        #[Assert\Range(min: 0, max: 180)]
        public int $doorsMinutesBefore = 30,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $layoutId = '',
    ) {
    }
}
