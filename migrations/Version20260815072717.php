<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815072717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute licencie.my_ffbad_categorie_age / my_ffbad_est_mineur — catégorie d'âge FFBaD récupérée via MyFFBaD, informative (n'écrase jamais date_naissance ni estMineur()).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie ADD my_ffbad_categorie_age VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD my_ffbad_est_mineur BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "licencie" DROP my_ffbad_categorie_age');
        $this->addSql('ALTER TABLE "licencie" DROP my_ffbad_est_mineur');
    }
}
