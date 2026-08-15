<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815081209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la catégorie d\'âge (liste FFBaD sélectionnable) au licencié.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie ADD categorie_age VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie DROP categorie_age');
    }
}
