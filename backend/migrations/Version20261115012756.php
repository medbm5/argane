<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115012756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: event, performance and inventory tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE event (
              id BINARY(16) NOT NULL,
              slug VARCHAR(140) NOT NULL,
              status VARCHAR(16) NOT NULL,
              subtitle VARCHAR(200) DEFAULT NULL,
              description LONGTEXT NOT NULL,
              poster_path VARCHAR(200) DEFAULT NULL,
              age_rating SMALLINT DEFAULT NULL,
              published_at DATETIME DEFAULT NULL,
              title VARCHAR(140) NOT NULL,
              category VARCHAR(16) NOT NULL,
              duration_minutes SMALLINT NOT NULL,
              created_by BINARY(16) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              venue_id BINARY(16) NOT NULL,
              INDEX idx_event_status (status, category),
              UNIQUE INDEX uniq_event_slug (venue_id, slug),
              INDEX IDX_3BAE0AA740A73EBA (venue_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE performance (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              published_at DATETIME DEFAULT NULL,
              cancelled_at DATETIME DEFAULT NULL,
              cancel_reason VARCHAR(255) DEFAULT NULL,
              starts_at DATETIME NOT NULL,
              doors_at DATETIME NOT NULL,
              sales_open_at DATETIME NOT NULL,
              sales_close_at DATETIME NOT NULL,
              event_id BINARY(16) NOT NULL,
              layout_id BINARY(16) NOT NULL,
              INDEX idx_performance_starts (starts_at),
              INDEX idx_performance_event (event_id, starts_at),
              INDEX IDX_82D7968171F7E88B (event_id),
              INDEX IDX_82D796818C22AA1A (layout_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE performance_seat (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              hold_id BINARY(16) DEFAULT NULL,
              hold_expires_at DATETIME DEFAULT NULL,
              ticket_id BINARY(16) DEFAULT NULL,
              updated_at DATETIME NOT NULL,
              performance_id BINARY(16) NOT NULL,
              seat_id BINARY(16) NOT NULL,
              zone_id BINARY(16) NOT NULL,
              INDEX idx_perf_seat_status (performance_id, status),
              INDEX idx_perf_seat_hold (hold_id),
              UNIQUE INDEX uniq_perf_seat (performance_id, seat_id),
              INDEX IDX_F5A7B85BB91ADEEE (performance_id),
              INDEX IDX_F5A7B85BC1DAFE35 (seat_id),
              INDEX IDX_F5A7B85B9F2C3FAB (zone_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE performance_zone_stock (
              id BINARY(16) NOT NULL,
              sold_count INT NOT NULL,
              capacity INT NOT NULL,
              performance_id BINARY(16) NOT NULL,
              zone_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_perf_zone_stock (performance_id, zone_id),
              INDEX IDX_95F1B518B91ADEEE (performance_id),
              INDEX IDX_95F1B5189F2C3FAB (zone_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              event
            ADD
              CONSTRAINT FK_3BAE0AA740A73EBA FOREIGN KEY (venue_id) REFERENCES venue (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance
            ADD
              CONSTRAINT FK_82D7968171F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance
            ADD
              CONSTRAINT FK_82D796818C22AA1A FOREIGN KEY (layout_id) REFERENCES hall_layout (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_seat
            ADD
              CONSTRAINT FK_F5A7B85BB91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_seat
            ADD
              CONSTRAINT FK_F5A7B85BC1DAFE35 FOREIGN KEY (seat_id) REFERENCES seat (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_seat
            ADD
              CONSTRAINT FK_F5A7B85B9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_zone_stock
            ADD
              CONSTRAINT FK_95F1B518B91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_zone_stock
            ADD
              CONSTRAINT FK_95F1B5189F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA740A73EBA');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D7968171F7E88B');
        $this->addSql('ALTER TABLE performance DROP FOREIGN KEY FK_82D796818C22AA1A');
        $this->addSql('ALTER TABLE performance_seat DROP FOREIGN KEY FK_F5A7B85BB91ADEEE');
        $this->addSql('ALTER TABLE performance_seat DROP FOREIGN KEY FK_F5A7B85BC1DAFE35');
        $this->addSql('ALTER TABLE performance_seat DROP FOREIGN KEY FK_F5A7B85B9F2C3FAB');
        $this->addSql('ALTER TABLE performance_zone_stock DROP FOREIGN KEY FK_95F1B518B91ADEEE');
        $this->addSql('ALTER TABLE performance_zone_stock DROP FOREIGN KEY FK_95F1B5189F2C3FAB');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE performance');
        $this->addSql('DROP TABLE performance_seat');
        $this->addSql('DROP TABLE performance_zone_stock');
    }
}
