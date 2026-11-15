<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115011647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Venue: venue, hall_layout, zone and seat tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE hall_layout (
              id BINARY(16) NOT NULL,
              is_default TINYINT NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              name VARCHAR(120) NOT NULL,
              created_at DATETIME NOT NULL,
              venue_id BINARY(16) NOT NULL,
              INDEX idx_layout_venue (venue_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE seat (
              id BINARY(16) NOT NULL,
              row_label VARCHAR(8) NOT NULL,
              number SMALLINT NOT NULL,
              x DOUBLE PRECISION NOT NULL,
              y DOUBLE PRECISION NOT NULL,
              is_accessible TINYINT NOT NULL,
              is_blocked TINYINT NOT NULL,
              zone_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_seat_zone_row_number (zone_id, row_label, number),
              INDEX IDX_3D5C36669F2C3FAB (zone_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE venue (
              id BINARY(16) NOT NULL,
              name VARCHAR(120) NOT NULL,
              slug VARCHAR(80) NOT NULL,
              city VARCHAR(80) NOT NULL,
              address VARCHAR(200) NOT NULL,
              timezone VARCHAR(64) NOT NULL,
              UNIQUE INDEX uniq_venue_slug (slug),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE zone (
              id BINARY(16) NOT NULL,
              code VARCHAR(32) NOT NULL,
              name VARCHAR(80) NOT NULL,
              kind VARCHAR(16) NOT NULL,
              color_token VARCHAR(32) NOT NULL,
              sort_order SMALLINT NOT NULL,
              shape LONGTEXT NOT NULL,
              capacity INT DEFAULT NULL,
              layout_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_zone_layout_code (layout_id, code),
              INDEX IDX_A0EBC0078C22AA1A (layout_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              hall_layout
            ADD
              CONSTRAINT FK_DCC33D8440A73EBA FOREIGN KEY (venue_id) REFERENCES venue (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              seat
            ADD
              CONSTRAINT FK_3D5C36669F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              zone
            ADD
              CONSTRAINT FK_A0EBC0078C22AA1A FOREIGN KEY (layout_id) REFERENCES hall_layout (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hall_layout DROP FOREIGN KEY FK_DCC33D8440A73EBA');
        $this->addSql('ALTER TABLE seat DROP FOREIGN KEY FK_3D5C36669F2C3FAB');
        $this->addSql('ALTER TABLE zone DROP FOREIGN KEY FK_A0EBC0078C22AA1A');
        $this->addSql('DROP TABLE hall_layout');
        $this->addSql('DROP TABLE seat');
        $this->addSql('DROP TABLE venue');
        $this->addSql('DROP TABLE zone');
    }
}
