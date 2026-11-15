<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;

/**
 * Who may sell which tariff, where, and how many (README "Who can sell which tariff"):
 *
 * | Tariff     | Online (customer) | Box office      | Organizer |
 * |------------|-------------------|-----------------|-----------|
 * | Plein      | yes               | yes             | yes       |
 * | Réduit     | yes (proof)       | yes             | yes       |
 * | Groupe     | yes, 10–30        | yes, 10–30      | yes       |
 * | Invitation | no                | yes, reason     | yes       |
 *
 * The rules are data on the Tariff (channels, allowedRoles, min/max, isFree);
 * this policy is the single place that interprets them. Roles must already be
 * expanded through the role hierarchy.
 */
final class TariffEligibilityPolicy
{
    /**
     * Tariffs a seller may offer on a channel, ignoring quantities (for display).
     *
     * @param list<Tariff> $tariffs
     * @param list<string> $roles
     *
     * @return list<Tariff>
     */
    public function offerable(array $tariffs, SalesChannel $channel, array $roles): array
    {
        return array_values(array_filter(
            $tariffs,
            fn (Tariff $t): bool => $t->isActive() && $t->isSoldOn($channel) && $this->roleAllows($t, $roles),
        ));
    }

    /**
     * @param list<string> $roles
     * @param int          $quantity number of tickets at this tariff in the order
     *
     * @throws BusinessRuleException
     */
    public function assertEligible(Tariff $tariff, SalesChannel $channel, array $roles, int $quantity, ?string $reason = null): void
    {
        $context = ['tariff' => $tariff->code()];

        if (!$tariff->isActive()) {
            throw BusinessRuleException::because('TARIFF_INACTIVE', sprintf('Le tarif %s n’est plus proposé.', $tariff->label()), $context);
        }
        if (!$tariff->isSoldOn($channel)) {
            throw BusinessRuleException::because('TARIFF_CHANNEL_NOT_ALLOWED', SalesChannel::ONLINE === $channel ? sprintf('Le tarif %s est disponible uniquement au guichet.', $tariff->label()) : sprintf('Le tarif %s n’est pas vendu au guichet.', $tariff->label()), $context, 403);
        }
        if (!$this->roleAllows($tariff, $roles)) {
            throw BusinessRuleException::because('TARIFF_ROLE_NOT_ALLOWED', sprintf('Vous ne pouvez pas vendre le tarif %s.', $tariff->label()), $context, 403);
        }
        if ($quantity < $tariff->minQuantity()) {
            throw BusinessRuleException::because('TARIFF_MIN_QUANTITY', sprintf('Le tarif %s s’applique à partir de %d places.', $tariff->label(), $tariff->minQuantity()), [...$context, 'min' => $tariff->minQuantity()]);
        }
        if (null !== $tariff->maxQuantity() && $quantity > $tariff->maxQuantity()) {
            throw BusinessRuleException::because('TARIFF_MAX_QUANTITY', sprintf('Le tarif %s est limité à %d places.', $tariff->label(), $tariff->maxQuantity()), [...$context, 'max' => $tariff->maxQuantity()]);
        }
        if ($tariff->isFree() && '' === trim((string) $reason)) {
            throw BusinessRuleException::because('INVITATION_REASON_REQUIRED', 'Indiquez le motif de l’invitation.', $context);
        }
    }

    /**
     * @param list<string> $roles
     */
    private function roleAllows(Tariff $tariff, array $roles): bool
    {
        return [] === $tariff->allowedRoles() || [] !== array_intersect($tariff->allowedRoles(), $roles);
    }
}
