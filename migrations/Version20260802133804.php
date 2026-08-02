<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802133804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE creneau ADD categorie VARCHAR(10) NOT NULL DEFAULT 'ADULTE'");
        $this->addSql('ALTER TABLE creneau ALTER categorie DROP DEFAULT');
        $this->addSql('ALTER TABLE creneau ADD classement_minimum INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creneau ADD recurrence_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE creneau ADD recurrence_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD photo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau DROP categorie');
        $this->addSql('ALTER TABLE creneau DROP classement_minimum');
        $this->addSql('ALTER TABLE creneau DROP recurrence_debut');
        $this->addSql('ALTER TABLE creneau DROP recurrence_fin');
        $this->addSql('ALTER TABLE "licencie" DROP photo');
    }
}
