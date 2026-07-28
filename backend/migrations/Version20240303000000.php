<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240303000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_parameter table (referenced by AdminController/SystemParameter entity but never migrated)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_parameter (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            param_key VARCHAR(100) NOT NULL,
            param_value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NOT NULL,
            description VARCHAR(500) NULL,
            type VARCHAR(20) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_system_parameter_key (param_key)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS system_parameter');
    }
}
