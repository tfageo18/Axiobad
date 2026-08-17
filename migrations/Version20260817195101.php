<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le rôle admin de groupe (et la date de début d'historique visible) aux participants de
 * conversation. Le créateur de chaque conversation existante devient admin ; à défaut (créateur
 * inconnu ou parti), le participant le plus ancien de la conversation en hérite.
 */
final class Version20260817195101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute est_admin et voit_historique_depuis à conversation_participant, désigne un admin pour les groupes existants";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant ADD est_admin BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE conversation_participant ALTER COLUMN est_admin DROP DEFAULT');
        $this->addSql('ALTER TABLE conversation_participant ADD voit_historique_depuis TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Le créateur de la conversation devient admin.
        $this->addSql(<<<'SQL'
            UPDATE conversation_participant cp
            SET est_admin = TRUE
            FROM conversation c
            WHERE cp.conversation_id = c.id AND c.createur_id IS NOT NULL AND cp.licencie_id = c.createur_id
        SQL);

        // Conversations sans admin désigné (créateur inconnu ou déjà parti) : le participant le
        // plus ancien (plus petit id de ligne) hérite du rôle.
        $this->addSql(<<<'SQL'
            UPDATE conversation_participant cp
            SET est_admin = TRUE
            FROM (
                SELECT DISTINCT ON (conversation_id) id, conversation_id
                FROM conversation_participant
                ORDER BY conversation_id, id ASC
            ) plus_ancien
            WHERE cp.id = plus_ancien.id
              AND NOT EXISTS (
                  SELECT 1 FROM conversation_participant cp2
                  WHERE cp2.conversation_id = plus_ancien.conversation_id AND cp2.est_admin = TRUE
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant DROP est_admin');
        $this->addSql('ALTER TABLE conversation_participant DROP voit_historique_depuis');
    }
}
