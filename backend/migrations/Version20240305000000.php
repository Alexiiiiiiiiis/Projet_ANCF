<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240305000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the schema with the Doctrine mapping (indexes, column types and index names never migrated)';
    }

    public function up(Schema $schema): void
    {
        // Les index déclarés sur les entités n'avaient jamais été créés : la purge des logs et
        // les statistiques admin filtrent sur created_at / status_code, l'historique sur created_at.
        $this->addSql('CREATE INDEX idx_log_date ON api_log (created_at)');
        $this->addSql('CREATE INDEX idx_log_status ON api_log (status_code)');
        $this->addSql('CREATE INDEX idx_sh_date ON search_history (created_at)');

        // Types resserrés sur ce que déclarent les entités. Sans risque de troncature :
        // transport_type vaut METRO/RER/TRAM/BUS, et sort_order comme result_count tiennent
        // largement dans un SMALLINT.
        $this->addSql('ALTER TABLE favorite_stop CHANGE transport_type transport_type VARCHAR(10) NOT NULL, CHANGE sort_order sort_order SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE search_history CHANGE search_query search_query VARCHAR(200) NOT NULL, CHANGE result_count result_count SMALLINT DEFAULT NULL');

        // Noms d'index alignés sur ceux attendus par Doctrine, sinon chaque diff les ressort.
        $this->addSql('ALTER TABLE favorite_stop RENAME INDEX uniq_user_stop TO uq_fav_user_stop');
        $this->addSql('ALTER TABLE search_history RENAME INDEX fk_search_history_user TO IDX_AA6B9FD1A76ED395');
        $this->addSql('ALTER TABLE system_parameter RENAME INDEX uniq_system_parameter_key TO UNIQ_294899AD35A9B410');

        // DC2Type:json n'a plus lieu d'être depuis DBAL 3 : le type json est natif.
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_log_date ON api_log');
        $this->addSql('DROP INDEX idx_log_status ON api_log');
        $this->addSql('DROP INDEX idx_sh_date ON search_history');
        $this->addSql('ALTER TABLE favorite_stop CHANGE transport_type transport_type VARCHAR(20) NOT NULL, CHANGE sort_order sort_order INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE search_history CHANGE search_query search_query VARCHAR(255) NOT NULL, CHANGE result_count result_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE favorite_stop RENAME INDEX uq_fav_user_stop TO UNIQ_user_stop');
        $this->addSql('ALTER TABLE search_history RENAME INDEX idx_aa6b9fd1a76ed395 TO FK_search_history_user');
        $this->addSql('ALTER TABLE system_parameter RENAME INDEX uniq_294899ad35a9b410 TO UNIQ_system_parameter_key');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
