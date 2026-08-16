<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816200341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la visibilité d'un événement (tous les licenciés, ou bureau uniquement).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE evenement ADD visibilite VARCHAR(20) NOT NULL DEFAULT 'TOUS'");
        $this->addSql('ALTER TABLE evenement ALTER COLUMN visibilite DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP visibilite');
    }
}
