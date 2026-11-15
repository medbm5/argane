<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115010112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: app_user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id BINARY(16) NOT NULL,
              roles JSON NOT NULL,
              created_at DATETIME NOT NULL,
              clerk_user_id VARCHAR(64) NOT NULL,
              email VARCHAR(180) NOT NULL,
              display_name VARCHAR(120) NOT NULL,
              INDEX idx_user_email (email),
              UNIQUE INDEX uniq_user_clerk_id (clerk_user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user');
    }
}
