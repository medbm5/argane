<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** A rectangle in layout coordinates (1000 × 700 viewBox). */
final readonly class Area
{
    public function __construct(
        #[Assert\Range(min: 0, max: 1000)]
        public float $x,
        #[Assert\Range(min: 0, max: 700)]
        public float $y,
        #[Assert\Positive]
        public float $width,
        #[Assert\Positive]
        public float $height,
    ) {
    }

    public function fitsInViewBox(): bool
    {
        return $this->x >= 0 && $this->y >= 0 && $this->x + $this->width <= 1000 && $this->y + $this->height <= 700;
    }

    /** Mirror across the vertical axis of the hall (for symmetric boxes). */
    public function mirrored(): self
    {
        return new self(1000 - $this->x - $this->width, $this->y, $this->width, $this->height);
    }
}
