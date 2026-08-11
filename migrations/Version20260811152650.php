<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811152650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD mot_de_passe_expose BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN mot_de_passe_expose DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD mot_de_passe_verifie_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP mot_de_passe_expose');
        $this->addSql('ALTER TABLE "licencie" DROP mot_de_passe_verifie_le');
    }
}
