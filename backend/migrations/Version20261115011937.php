<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115011937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Venue: layout locking and clone lineage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              hall_layout
            ADD
              locked_at DATETIME DEFAULT NULL,
            ADD
              cloned_from_id BINARY(16) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              hall_layout
            ADD
              CONSTRAINT FK_DCC33D84B2CF0654 FOREIGN KEY (cloned_from_id) REFERENCES hall_layout (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_DCC33D84B2CF0654 ON hall_layout (cloned_from_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hall_layout DROP FOREIGN KEY FK_DCC33D84B2CF0654');
        $this->addSql('DROP INDEX IDX_DCC33D84B2CF0654 ON hall_layout');
        $this->addSql('ALTER TABLE hall_layout DROP locked_at, DROP cloned_from_id');
    }
}
