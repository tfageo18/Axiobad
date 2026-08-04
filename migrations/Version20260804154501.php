<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804154501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD consentement_donnees_sante BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN consentement_donnees_sante DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD suppression_demandee_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP consentement_donnees_sante');
        $this->addSql('ALTER TABLE "licencie" DROP suppression_demandee_le');
    }
}
