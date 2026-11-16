<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116105858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uploaded posters, stored in the database (no persistent disk on the free plan)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE poster (id BINARY(16) NOT NULL, mime_type VARCHAR(32) NOT NULL, content LONGBLOB NOT NULL, byte_size INT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE poster');
    }
}
