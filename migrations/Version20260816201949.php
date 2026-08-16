<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816201949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Remplace Evenement::visibilite (TOUS/BUREAU) par rolesVisibles, une liste de rôles cumulable (ex. cordeurs + gestion de stock).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE evenement ADD roles_visibles JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE evenement ALTER COLUMN roles_visibles DROP DEFAULT');

        // Migration des données : "BUREAU" -> ["ROLE_BUREAU"], "TOUS" -> [] (déjà la valeur par défaut).
        $this->addSql("UPDATE evenement SET roles_visibles = '[\"ROLE_BUREAU\"]' WHERE visibilite = 'BUREAU'");

        $this->addSql('ALTER TABLE evenement DROP visibilite');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE evenement ADD visibilite VARCHAR(20) NOT NULL DEFAULT 'TOUS'");
        $this->addSql('ALTER TABLE evenement ALTER COLUMN visibilite DROP DEFAULT');

        $this->addSql("UPDATE evenement SET visibilite = 'BUREAU' WHERE roles_visibles::jsonb ? 'ROLE_BUREAU'");

        $this->addSql('ALTER TABLE evenement DROP roles_visibles');
    }
}
