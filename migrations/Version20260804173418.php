<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Désactive rétroactivement les notifications automatiques pour tous les licenciés existants,
 * conformément au nouveau réglage par défaut (opt-in plutôt qu'opt-out).
 */
final class Version20260804173418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Désactive les notifications automatiques par défaut pour les licenciés existants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE licencie SET notifications_activees = false');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN notifications_activees SET DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie ALTER COLUMN notifications_activees SET DEFAULT true');
    }
}
