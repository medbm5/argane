<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115013130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pricing: tariff and performance_price tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE performance_price (
              id BINARY(16) NOT NULL,
              amount_cents INT NOT NULL,
              performance_id BINARY(16) NOT NULL,
              zone_id BINARY(16) NOT NULL,
              tariff_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_perf_price (
                performance_id, zone_id, tariff_id
              ),
              INDEX IDX_92689546B91ADEEE (performance_id),
              INDEX IDX_926895469F2C3FAB (zone_id),
              INDEX IDX_9268954692348FD2 (tariff_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tariff (
              id BINARY(16) NOT NULL,
              channels JSON NOT NULL,
              allowed_roles JSON NOT NULL,
              active TINYINT NOT NULL,
              sort_order SMALLINT NOT NULL,
              code VARCHAR(32) NOT NULL,
              label VARCHAR(80) NOT NULL,
              min_quantity SMALLINT NOT NULL,
              max_quantity SMALLINT DEFAULT NULL,
              requires_proof_at_entry TINYINT NOT NULL,
              is_free TINYINT NOT NULL,
              description VARCHAR(255) DEFAULT NULL,
              venue_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_tariff_code (venue_id, code),
              INDEX IDX_9465207D40A73EBA (venue_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_price
            ADD
              CONSTRAINT FK_92689546B91ADEEE FOREIGN KEY (performance_id) REFERENCES performance (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_price
            ADD
              CONSTRAINT FK_926895469F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              performance_price
            ADD
              CONSTRAINT FK_9268954692348FD2 FOREIGN KEY (tariff_id) REFERENCES tariff (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tariff
            ADD
              CONSTRAINT FK_9465207D40A73EBA FOREIGN KEY (venue_id) REFERENCES venue (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE performance_price DROP FOREIGN KEY FK_92689546B91ADEEE');
        $this->addSql('ALTER TABLE performance_price DROP FOREIGN KEY FK_926895469F2C3FAB');
        $this->addSql('ALTER TABLE performance_price DROP FOREIGN KEY FK_9268954692348FD2');
        $this->addSql('ALTER TABLE tariff DROP FOREIGN KEY FK_9465207D40A73EBA');
        $this->addSql('DROP TABLE performance_price');
        $this->addSql('DROP TABLE tariff');
    }
}
