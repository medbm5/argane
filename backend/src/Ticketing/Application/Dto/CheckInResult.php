<?php

declare(strict_types=1);

namespace App\Ticketing\Application\Dto;

/**
 * What the scanner shows: a verdict (green / amber / red) and enough about the ticket
 * for the steward to act (seat to point at, proof to ask for).
 */
final readonly class CheckInResult
{
    public const string ADMITTED = 'ADMITTED';
    public const string ALREADY_USED = 'ALREADY_USED';
    public const string WRONG_PERFORMANCE = 'WRONG_PERFORMANCE';
    public const string NOT_VALID = 'NOT_VALID';
    public const string INVALID = 'INVALID';

    public function __construct(
        public string $verdict,
        public string $message,
        public ?string $ticketId = null,
        public ?string $reference = null,
        public ?string $zone = null,
        public ?string $seat = null,
        public ?string $tariff = null,
        public bool $requiresProof = false,
        public ?string $holder = null,
        /** When it was first scanned (ALREADY_USED). */
        public ?string $checkedInAt = null,
        /** The ticket's real performance (WRONG_PERFORMANCE). */
        public ?string $ticketPerformanceStartsAt = null,
        public ?string $ticketEventTitle = null,
        public int $admitted = 0,
        public int $expected = 0,
    ) {
    }
}
