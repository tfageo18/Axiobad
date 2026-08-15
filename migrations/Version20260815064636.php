<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815064636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute parametres_club (réglages globaux, une seule ligne) : nom du club et nom du club sur MyFFBaD.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parametres_club (id INT NOT NULL, nom_club VARCHAR(150) DEFAULT NULL, nom_club_my_ffbad VARCHAR(150) DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parametres_club');
    }
}
