<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802222059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau ADD capacite_max INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creneau ADD delai_annulation_heures INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presence ADD statut_inscription VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE presence ADD promotion_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Les réponses "je viens" déjà enregistrées sont considérées confirmées (pas de capacité avant cette version).
        $this->addSql("UPDATE presence SET statut_inscription = 'CONFIRMEE' WHERE present = true");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau DROP capacite_max');
        $this->addSql('ALTER TABLE creneau DROP delai_annulation_heures');
        $this->addSql('ALTER TABLE presence DROP statut_inscription');
        $this->addSql('ALTER TABLE presence DROP promotion_expires_at');
    }
}
