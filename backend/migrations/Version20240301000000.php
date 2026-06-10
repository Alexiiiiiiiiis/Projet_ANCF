<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240301000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial Transport ANCF schema (users, favorite_stops, search_history, api_log)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            roles JSON NOT NULL COMMENT \'(DC2Type:json)\',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_8D93D649E7927C74 (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE favorite_stop (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            stop_id VARCHAR(100) NOT NULL,
            stop_name VARCHAR(200) NOT NULL,
            line_code VARCHAR(20) NOT NULL,
            transport_type VARCHAR(20) NOT NULL,
            added_at DATETIME NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_user_stop (user_id, stop_id),
            CONSTRAINT FK_favorite_stop_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE search_history (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            search_query VARCHAR(255) NOT NULL,
            result_count INT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT FK_search_history_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE api_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            endpoint VARCHAR(255) NOT NULL,
            http_method VARCHAR(10) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL,
            response_time_ms SMALLINT UNSIGNED NOT NULL,
            error_message LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS favorite_stop');
        $this->addSql('DROP TABLE IF EXISTS search_history');
        $this->addSql('DROP TABLE IF EXISTS api_log');
        $this->addSql('DROP TABLE IF EXISTS user');
    }
}
