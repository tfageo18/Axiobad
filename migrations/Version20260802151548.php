<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802151548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gymnase_porteur_cles (gymnase_id INT NOT NULL, licencie_id INT NOT NULL, PRIMARY KEY (gymnase_id, licencie_id))');
        $this->addSql('CREATE INDEX IDX_3C8AA230F4F4DDD0 ON gymnase_porteur_cles (gymnase_id)');
        $this->addSql('CREATE INDEX IDX_3C8AA230B56DCD74 ON gymnase_porteur_cles (licencie_id)');
        $this->addSql('ALTER TABLE gymnase_porteur_cles ADD CONSTRAINT FK_3C8AA230F4F4DDD0 FOREIGN KEY (gymnase_id) REFERENCES gymnase (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gymnase_porteur_cles ADD CONSTRAINT FK_3C8AA230B56DCD74 FOREIGN KEY (licencie_id) REFERENCES "licencie" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE creneau ALTER classement_minimum TYPE VARCHAR(5)');
        $this->addSql('ALTER TABLE gymnase ADD telephone VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ALTER classement_simple TYPE VARCHAR(5)');
        $this->addSql('ALTER TABLE licencie ALTER classement_double TYPE VARCHAR(5)');
        $this->addSql('ALTER TABLE licencie ALTER classement_mixte TYPE VARCHAR(5)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gymnase_porteur_cles DROP CONSTRAINT FK_3C8AA230F4F4DDD0');
        $this->addSql('ALTER TABLE gymnase_porteur_cles DROP CONSTRAINT FK_3C8AA230B56DCD74');
        $this->addSql('DROP TABLE gymnase_porteur_cles');
        $this->addSql('ALTER TABLE creneau ALTER classement_minimum TYPE INT');
        $this->addSql('ALTER TABLE gymnase DROP telephone');
        $this->addSql('ALTER TABLE "licencie" ALTER classement_simple TYPE INT');
        $this->addSql('ALTER TABLE "licencie" ALTER classement_double TYPE INT');
        $this->addSql('ALTER TABLE "licencie" ALTER classement_mixte TYPE INT');
    }
}
