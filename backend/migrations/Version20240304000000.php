<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240304000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add favorite_stop.kind so a favorite can be a line as well as a stop';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 'STOP' : les favoris déjà enregistrés sont tous des arrêts.
        $this->addSql("ALTER TABLE favorite_stop ADD kind VARCHAR(10) DEFAULT 'STOP' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favorite_stop DROP kind');
    }
}
