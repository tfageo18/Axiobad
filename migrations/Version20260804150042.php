<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804150042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD personnes_autorisees_recuperation TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD contact_urgence_nom VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD contact_urgence_telephone VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD autorisation_sortie_seul BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN autorisation_sortie_seul DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD droit_image BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN droit_image DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD informations_sante TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD responsable_legal1_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD responsable_legal2_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ALTER email DROP NOT NULL');
        $this->addSql('ALTER TABLE licencie ADD CONSTRAINT FK_3B75561239FF2EE9 FOREIGN KEY (responsable_legal1_id) REFERENCES "licencie" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE licencie ADD CONSTRAINT FK_3B7556122B4A8107 FOREIGN KEY (responsable_legal2_id) REFERENCES "licencie" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3B75561239FF2EE9 ON licencie (responsable_legal1_id)');
        $this->addSql('CREATE INDEX IDX_3B7556122B4A8107 ON licencie (responsable_legal2_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP CONSTRAINT FK_3B75561239FF2EE9');
        $this->addSql('ALTER TABLE "licencie" DROP CONSTRAINT FK_3B7556122B4A8107');
        $this->addSql('DROP INDEX IDX_3B75561239FF2EE9');
        $this->addSql('DROP INDEX IDX_3B7556122B4A8107');
        $this->addSql('ALTER TABLE "licencie" DROP personnes_autorisees_recuperation');
        $this->addSql('ALTER TABLE "licencie" DROP contact_urgence_nom');
        $this->addSql('ALTER TABLE "licencie" DROP contact_urgence_telephone');
        $this->addSql('ALTER TABLE "licencie" DROP autorisation_sortie_seul');
        $this->addSql('ALTER TABLE "licencie" DROP droit_image');
        $this->addSql('ALTER TABLE "licencie" DROP informations_sante');
        $this->addSql('ALTER TABLE "licencie" DROP responsable_legal1_id');
        $this->addSql('ALTER TABLE "licencie" DROP responsable_legal2_id');
        $this->addSql('ALTER TABLE "licencie" ALTER email SET NOT NULL');
    }
}
