<?php

declare(strict_types=1);

namespace App\Integration\Application;

use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Rebuilds the two tabs of the box office spreadsheet from the database:
 * - Ventes: one row per ticket (reference, dates, event, zone, seat, tariff, channel,
 *   price, status);
 * - Synthèse: fill and revenue per performance.
 *
 * A full rewrite (clear + write) instead of incremental edits: at a few thousand rows
 * it is fast, idempotent, and the sheet can never drift from the database.
 */
final class SheetsSync
{
    public const string SALES_TAB = 'Ventes';
    public const string SUMMARY_TAB = 'Synthèse';
    private const string STATUS_KEY = 'integration.sheets.status';

    public function __construct(
        private readonly SheetsClient $sheets,
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Runs the sync unless one already started strictly after `$requestedAt` (debounce: a
     * burst of sales produces one rewrite; a change in the same second as a sync is never
     * skipped). Returns false when skipped.
     */
    public function sync(?float $requestedAt = null): bool
    {
        $status = $this->status();
        if (!$this->sheets->isConfigured()) {
            return false;
        }
        if (null !== $requestedAt && null !== $status['lastSyncAt'] && (new DateTimeImmutable($status['lastSyncAt']))->getTimestamp() > (int) floor($requestedAt)) {
            return false;
        }

        $startedAt = $this->clock->now();
        try {
            $sales = $this->salesRows();
            $summary = $this->summaryRows();
            $this->sheets->replaceTab(self::SALES_TAB, $sales);
            $this->sheets->replaceTab(self::SUMMARY_TAB, $summary);
            $this->saveStatus(['lastSyncAt' => $startedAt->format(\DATE_ATOM), 'lastError' => null, 'rows' => \count($sales) - 1]);
        } catch (Throwable $e) {
            $this->logger->error('Google Sheets sync failed', ['exception' => $e]);
            $this->saveStatus([...$status, 'lastError' => mb_substr($e->getMessage(), 0, 300), 'lastAttemptAt' => $startedAt->format(\DATE_ATOM)]);
            throw $e;
        }

        return true;
    }

    /**
     * @return array{configured: bool, url: ?string, lastSyncAt: ?string, lastAttemptAt?: string, lastError: ?string, rows: int}
     */
    public function status(): array
    {
        $item = $this->cache->getItem(self::STATUS_KEY);
        /** @var array{lastSyncAt?: ?string, lastAttemptAt?: string, lastError?: ?string, rows?: int} $saved */
        $saved = $item->isHit() && \is_array($item->get()) ? $item->get() : [];

        return [
            'configured' => $this->sheets->isConfigured(),
            'url' => $this->sheets->url(),
            'lastSyncAt' => $saved['lastSyncAt'] ?? null,
            ...(isset($saved['lastAttemptAt']) ? ['lastAttemptAt' => $saved['lastAttemptAt']] : []),
            'lastError' => $saved['lastError'] ?? null,
            'rows' => $saved['rows'] ?? 0,
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function salesRows(): array
    {
        $rows = [['Référence', 'Commandé le', 'Spectacle', 'Représentation', 'Zone', 'Place', 'Tarif', 'Canal', 'Prix', 'Statut billet', 'Statut commande']];
        $result = $this->connection->executeQuery(
            "SELECT o.reference, o.created_at, e.title, p.starts_at, t.zone_label, t.seat_label, t.tariff_label,
                    o.channel, t.unit_price_cents, t.status AS ticket_status, o.status AS order_status
               FROM ticket t JOIN customer_order o ON o.id = t.order_id
               JOIN performance p ON p.id = t.performance_id JOIN event e ON e.id = p.event_id
              WHERE o.status NOT IN ('PENDING_PAYMENT', 'EXPIRED', 'FAILED')
           ORDER BY o.created_at, o.reference, t.seat_label",
        );
        while (false !== ($r = $result->fetchAssociative())) {
            $rows[] = [
                (string) $r['reference'],
                self::local($r['created_at']),
                (string) $r['title'],
                self::local($r['starts_at']),
                (string) $r['zone_label'],
                null === $r['seat_label'] ? 'debout' : (string) $r['seat_label'],
                (string) $r['tariff_label'],
                'ONLINE' === $r['channel'] ? 'en ligne' : 'guichet',
                round(((int) $r['unit_price_cents']) / 100, 2),
                self::ticketStatus((string) $r['ticket_status']),
                self::orderStatus((string) $r['order_status']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function summaryRows(): array
    {
        $rows = [['Spectacle', 'Représentation', 'Statut', 'Vendus', 'Capacité', 'Remplissage', 'Recettes', 'Entrées scannées']];
        $data = $this->connection->fetchAllAssociative(
            "SELECT e.title, p.starts_at, p.status, p.cancelled_at,
                    (SELECT COUNT(*) FROM ticket t WHERE t.performance_id = p.id AND t.status IN ('VALID', 'CHECKED_IN')) AS sold,
                    (SELECT COUNT(*) FROM ticket t WHERE t.performance_id = p.id AND t.status = 'CHECKED_IN') AS scanned,
                    (SELECT COALESCE(SUM(t.unit_price_cents), 0) FROM ticket t WHERE t.performance_id = p.id AND t.status IN ('VALID', 'CHECKED_IN')) AS revenue,
                    (SELECT COUNT(*) FROM performance_seat ps WHERE ps.performance_id = p.id AND ps.status <> 'BLOCKED')
                  + (SELECT COALESCE(SUM(s.capacity), 0) FROM performance_zone_stock s WHERE s.performance_id = p.id) AS capacity
               FROM performance p JOIN event e ON e.id = p.event_id
              WHERE p.published_at IS NOT NULL
           ORDER BY p.starts_at",
        );
        foreach ($data as $r) {
            $capacity = (int) $r['capacity'];
            $sold = (int) $r['sold'];
            $rows[] = [
                (string) $r['title'],
                self::local($r['starts_at']),
                null !== $r['cancelled_at'] ? 'annulée' : match ((string) $r['status']) {
                    'ON_SALE' => 'en vente',
                    'SALES_CLOSED' => 'ventes closes',
                    default => 'programmée',
                },
                $sold,
                $capacity,
                $capacity > 0 ? round($sold / $capacity, 4) : 0,
                round(((int) $r['revenue']) / 100, 2),
                (int) $r['scanned'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function saveStatus(array $status): void
    {
        $item = $this->cache->getItem(self::STATUS_KEY);
        $item->set($status);
        $this->cache->save($item);
    }

    private static function local(mixed $utc): string
    {
        return VenueTime::toVenue(new DateTimeImmutable((string) $utc, new DateTimeZone('UTC')))->format('d/m/Y H:i');
    }

    private static function ticketStatus(string $status): string
    {
        return ['VALID' => 'valide', 'CHECKED_IN' => 'entré', 'CANCELLED' => 'annulé', 'REFUNDED' => 'remboursé', 'PENDING' => 'en attente'][$status] ?? $status;
    }

    private static function orderStatus(string $status): string
    {
        return ['PAID' => 'payée', 'CANCELLED' => 'annulée', 'REFUNDED' => 'remboursée', 'PARTIALLY_REFUNDED' => 'remboursée en partie'][$status] ?? $status;
    }
}
