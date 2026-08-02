<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802160733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau ADD loisir BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE creneau ADD competiteur BOOLEAN NOT NULL DEFAULT false');
        $this->addSql("UPDATE creneau SET loisir = (type = 'LOISIR'), competiteur = (type = 'COMPETITEUR')");
        $this->addSql('ALTER TABLE creneau ALTER loisir DROP DEFAULT');
        $this->addSql('ALTER TABLE creneau ALTER competiteur DROP DEFAULT');
        $this->addSql('ALTER TABLE creneau DROP type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau ADD type VARCHAR(15) NOT NULL');
        $this->addSql('ALTER TABLE creneau DROP loisir');
        $this->addSql('ALTER TABLE creneau DROP competiteur');
    }
}
