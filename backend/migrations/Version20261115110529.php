<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115110529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Covering indexes for analytics queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_order_paid ON customer_order (paid_at, channel, status)');
        $this->addSql('CREATE INDEX idx_order_cancelled ON customer_order (cancelled_at, status)');
        $this->addSql('CREATE INDEX idx_performance_layout ON performance (layout_id, starts_at)');
        $this->addSql('CREATE INDEX idx_ticket_order_status ON ticket (order_id, status, unit_price_cents)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_paid ON customer_order');
        $this->addSql('DROP INDEX idx_order_cancelled ON customer_order');
        $this->addSql('DROP INDEX idx_performance_layout ON performance');
        $this->addSql('DROP INDEX idx_ticket_order_status ON ticket');
    }
}
