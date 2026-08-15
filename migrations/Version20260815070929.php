<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815070929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute licencie.my_ffbad_derniere_sync_le / my_ffbad_sync_reussie pour afficher le statut de la synchronisation MyFFBaD sur la fiche licencié et Mon compte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie ADD my_ffbad_derniere_sync_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD my_ffbad_sync_reussie BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "licencie" DROP my_ffbad_derniere_sync_le');
        $this->addSql('ALTER TABLE "licencie" DROP my_ffbad_sync_reussie');
    }
}
