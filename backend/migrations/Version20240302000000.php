<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240302000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reset_password_token and reset_password_expires_at to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD reset_password_token VARCHAR(100) NULL, ADD reset_password_expires_at DATETIME NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN reset_password_token, DROP COLUMN reset_password_expires_at');
    }
}
