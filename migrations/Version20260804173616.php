<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804173616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ALTER notifications_activees DROP DEFAULT');
        $this->addSql('ALTER TABLE stock_vetement ADD lieu_stockage VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_volant ADD lieu_stockage VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" ALTER notifications_activees SET DEFAULT false');
        $this->addSql('ALTER TABLE stock_vetement DROP lieu_stockage');
        $this->addSql('ALTER TABLE stock_volant DROP lieu_stockage');
    }
}
