<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;
use App\Venue\Domain\Model\Venue;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A kind of ticket (Plein, Réduit, Groupe, Invitation…) with who may sell it, where,
 * and in which quantities. Prices live in PerformancePrice (zone × tariff × performance).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tariff')]
#[ORM\UniqueConstraint(name: 'uniq_tariff_code', columns: ['venue_id', 'code'])]
class Tariff
{
    public const string FULL = 'FULL';
    public const string REDUCED = 'REDUCED';
    public const string GROUP = 'GROUP';
    public const string INVITATION = 'INVITATION';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** @var list<string> SalesChannel values */
    #[ORM\Column(type: Types::JSON)]
    private array $channels;

    /** @var list<string> roles allowed to sell it; empty = anyone allowed on the channel */
    #[ORM\Column(type: Types::JSON)]
    private array $allowedRoles;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $sortOrder = 0;

    /**
     * @param list<SalesChannel> $channels
     * @param list<string>       $allowedRoles
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Venue::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Venue $venue,
        #[ORM\Column(length: 32)]
        private string $code,
        #[ORM\Column(length: 80)]
        private string $label,
        array $channels,
        array $allowedRoles = [],
        #[ORM\Column(type: Types::SMALLINT)]
        private int $minQuantity = 1,
        #[ORM\Column(type: Types::SMALLINT, nullable: true)]
        private ?int $maxQuantity = null,
        #[ORM\Column]
        private bool $requiresProofAtEntry = false,
        #[ORM\Column]
        private bool $isFree = false,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $description = null,
    ) {
        $this->id = Uuid::v7();
        $this->code = strtoupper($code);
        $this->channels = self::channelValues($channels);
        $this->allowedRoles = array_values(array_unique($allowedRoles));
        $this->assertQuantities($minQuantity, $maxQuantity);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function venue(): Venue
    {
        return $this->venue;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return list<SalesChannel>
     */
    public function channels(): array
    {
        return array_map(static fn (string $c): SalesChannel => SalesChannel::from($c), $this->channels);
    }

    public function isSoldOn(SalesChannel $channel): bool
    {
        return \in_array($channel->value, $this->channels, true);
    }

    /**
     * @return list<string>
     */
    public function allowedRoles(): array
    {
        return $this->allowedRoles;
    }

    public function minQuantity(): int
    {
        return $this->minQuantity;
    }

    public function maxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function requiresProofAtEntry(): bool
    {
        return $this->requiresProofAtEntry;
    }

    public function isFree(): bool
    {
        return $this->isFree;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @param list<SalesChannel> $channels
     * @param list<string>       $allowedRoles
     */
    public function update(
        string $label,
        array $channels,
        array $allowedRoles,
        int $minQuantity,
        ?int $maxQuantity,
        bool $requiresProofAtEntry,
        bool $isFree,
        ?string $description,
        int $sortOrder,
    ): void {
        $this->assertQuantities($minQuantity, $maxQuantity);
        $this->label = $label;
        $this->channels = self::channelValues($channels);
        $this->allowedRoles = array_values(array_unique($allowedRoles));
        $this->minQuantity = $minQuantity;
        $this->maxQuantity = $maxQuantity;
        $this->requiresProofAtEntry = $requiresProofAtEntry;
        $this->isFree = $isFree;
        $this->description = $description;
        $this->sortOrder = $sortOrder;
    }

    public function activate(bool $active = true): void
    {
        $this->active = $active;
    }

    /**
     * @param list<SalesChannel> $channels
     *
     * @return list<string>
     */
    private static function channelValues(array $channels): array
    {
        if ([] === $channels) {
            throw BusinessRuleException::because('TARIFF_NO_CHANNEL', 'Un tarif doit être vendu sur au moins un canal.');
        }

        return array_values(array_unique(array_map(static fn (SalesChannel $c): string => $c->value, $channels)));
    }

    private function assertQuantities(int $min, ?int $max): void
    {
        if ($min < 1 || (null !== $max && $max < $min)) {
            throw BusinessRuleException::because('TARIFF_INVALID_QUANTITY', 'Quantités minimum et maximum incohérentes.');
        }
    }
}
