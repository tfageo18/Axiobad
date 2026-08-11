<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811142717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD equipe_preferee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD CONSTRAINT FK_3B755612F6B7061B FOREIGN KEY (equipe_preferee_id) REFERENCES equipe (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3B755612F6B7061B ON licencie (equipe_preferee_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP CONSTRAINT FK_3B755612F6B7061B');
        $this->addSql('DROP INDEX IDX_3B755612F6B7061B');
        $this->addSql('ALTER TABLE "licencie" DROP equipe_preferee_id');
    }
}
