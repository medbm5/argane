<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115013646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Booking and Payment: holds, orders, tickets, payments, refunds, webhook log, order sequence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_order (
              id BINARY(16) NOT NULL,
              status VARCHAR(24) NOT NULL,
              total_cents INT NOT NULL,
              walk_in_name VARCHAR(120) DEFAULT NULL,
              walk_in_email VARCHAR(180) DEFAULT NULL,
              note VARCHAR(255) DEFAULT NULL,
              paid_at DATETIME DEFAULT NULL,
              cancelled_at DATETIME DEFAULT NULL,
              reference VARCHAR(20) NOT NULL,
              channel VARCHAR(16) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              created_at DATETIME NOT NULL,
              customer_id BINARY(16) DEFAULT NULL,
              seller_id BINARY(16) DEFAULT NULL,
              performance_id BINARY(16) NOT NULL,
              hold_id BINARY(16) DEFAULT NULL,
              INDEX idx_order_customer (customer_id, created_at),
              INDEX idx_order_perf_status (performance_id, status),
              INDEX idx_order_status_created (status, created_at),
              UNIQUE INDEX uniq_order_reference (reference),
              INDEX IDX_3B1CE6A3B91ADEEE (performance_id),
              INDEX IDX_3B1CE6A3AD118649 (hold_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE hold (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              channel VARCHAR(16) NOT NULL,
              expires_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              performance_id BINARY(16) NOT NULL,
              INDEX idx_hold_user_perf (user_id, performance_id, status),
              INDEX idx_hold_status_expiry (status, expires_at),
              INDEX IDX_1FCA0D07B91ADEEE (performance_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              settled_at DATETIME DEFAULT NULL,
              provider VARCHAR(16) NOT NULL,
              amount_cents INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              created_at DATETIME NOT NULL,
              provider_ref VARCHAR(255) DEFAULT NULL,
              order_id BINARY(16) NOT NULL,
              INDEX idx_payment_order (order_id),
              UNIQUE INDEX uniq_payment_provider_ref (provider, provider_ref),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE refund (
              id BINARY(16) NOT NULL,
              status VARCHAR(20) NOT NULL,
              provider_ref VARCHAR(255) DEFAULT NULL,
              processed_at DATETIME DEFAULT NULL,
              failure_reason VARCHAR(255) DEFAULT NULL,
              amount_cents INT NOT NULL,
              reason VARCHAR(255) NOT NULL,
              requested_by BINARY(16) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              order_id BINARY(16) NOT NULL,
              payment_id BINARY(16) DEFAULT NULL,
              INDEX idx_refund_order (order_id),
              INDEX idx_refund_status (status),
              INDEX IDX_5B2C14584C3A3BB (payment_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE standing_hold_line (
              id BINARY(16) NOT NULL,
              quantity SMALLINT NOT NULL,
              hold_id BINARY(16) NOT NULL,
              zone_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_standing_line (hold_id, zone_id),
              INDEX IDX_FCB9D631AD118649 (hold_id),
              INDEX IDX_FCB9D6319F2C3FAB (zone_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stripe_webhook_event (
              id VARCHAR(255) NOT NULL,
              type VARCHAR(80) NOT NULL,
              processed_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ticket (
              active_seat_key BINARY(16) GENERATED ALWAYS AS (
                IF(
                  status IN ('VALID', 'CHECKED_IN'),
                  performance_seat_id,
                  NULL
                )
              ) STORED,
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              qr_nonce VARCHAR(32) NOT NULL,
              checked_in_at DATETIME DEFAULT NULL,
              checked_in_by BINARY(16) DEFAULT NULL,
              tariff_label VARCHAR(80) NOT NULL,
              zone_label VARCHAR(80) NOT NULL,
              seat_label VARCHAR(16) DEFAULT NULL,
              requires_proof TINYINT NOT NULL,
              unit_price_cents INT NOT NULL,
              order_id BINARY(16) NOT NULL,
              performance_id BINARY(16) NOT NULL,
              zone_id BINARY(16) NOT NULL,
              tariff_id BINARY(16) NOT NULL,
              performance_seat_id BINARY(16) DEFAULT NULL,
              INDEX idx_ticket_perf_status (performance_id, status),
              UNIQUE INDEX uniq_ticket_active_seat (active_seat_key),
              INDEX IDX_97A0ADA38D9F6D38 (order_id),
              INDEX IDX_97A0ADA3B91ADEEE (performance_id),
              INDEX IDX_97A0ADA39F2C3FAB (zone_id),
              INDEX IDX_97A0ADA392348FD2 (tariff_id),
              INDEX IDX_97A0ADA36206166F (performance_seat_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              customer_order
            ADD
              CONSTRAINT FK_3B1CE6A3B91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              customer_order
            ADD
              CONSTRAINT FK_3B1CE6A3AD118649 FOREIGN KEY (hold_id) REFERENCES hold (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              hold
            ADD
              CONSTRAINT FK_1FCA0D07B91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment
            ADD
              CONSTRAINT FK_6D28840D8D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              refund
            ADD
              CONSTRAINT FK_5B2C14588D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              refund
            ADD
              CONSTRAINT FK_5B2C14584C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              standing_hold_line
            ADD
              CONSTRAINT FK_FCB9D631AD118649 FOREIGN KEY (hold_id) REFERENCES hold (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              standing_hold_line
            ADD
              CONSTRAINT FK_FCB9D6319F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ticket
            ADD
              CONSTRAINT FK_97A0ADA38D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ticket
            ADD
              CONSTRAINT FK_97A0ADA3B91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ticket
            ADD
              CONSTRAINT FK_97A0ADA39F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ticket
            ADD
              CONSTRAINT FK_97A0ADA392348FD2 FOREIGN KEY (tariff_id) REFERENCES tariff (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ticket
            ADD
              CONSTRAINT FK_97A0ADA36206166F FOREIGN KEY (performance_seat_id) REFERENCES performance_seat (id)
        SQL);
        // Per-year counter behind public order references (ARG-26-000123); outside the ORM.
        $this->addSql(<<<'SQL'
            CREATE TABLE order_sequence (
              year SMALLINT UNSIGNED NOT NULL,
              next_value INT UNSIGNED NOT NULL,
              PRIMARY KEY (year)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3B91ADEEE');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3AD118649');
        $this->addSql('ALTER TABLE hold DROP FOREIGN KEY FK_1FCA0D07B91ADEEE');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D8D9F6D38');
        $this->addSql('ALTER TABLE refund DROP FOREIGN KEY FK_5B2C14588D9F6D38');
        $this->addSql('ALTER TABLE refund DROP FOREIGN KEY FK_5B2C14584C3A3BB');
        $this->addSql('ALTER TABLE standing_hold_line DROP FOREIGN KEY FK_FCB9D631AD118649');
        $this->addSql('ALTER TABLE standing_hold_line DROP FOREIGN KEY FK_FCB9D6319F2C3FAB');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA38D9F6D38');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B91ADEEE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA39F2C3FAB');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA392348FD2');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA36206166F');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE hold');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE refund');
        $this->addSql('DROP TABLE standing_hold_line');
        $this->addSql('DROP TABLE stripe_webhook_event');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE order_sequence');
    }
}
