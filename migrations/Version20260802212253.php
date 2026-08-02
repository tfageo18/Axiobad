<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802212253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD genre VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_vetement ADD prix_unitaire VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_volant ADD prix_unitaire VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP genre');
        $this->addSql('ALTER TABLE stock_vetement DROP prix_unitaire');
        $this->addSql('ALTER TABLE stock_volant DROP prix_unitaire');
    }
}
