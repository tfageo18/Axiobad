<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815064920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute parametres_club.url_effectif_my_ffbad (URL de la page MyFFBaD listant l'effectif du club, fournie par le bureau).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres_club ADD url_effectif_my_ffbad VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametres_club DROP url_effectif_my_ffbad');
    }
}
