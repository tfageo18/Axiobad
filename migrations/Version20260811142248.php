<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811142248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rencontre_interclub ADD gymnase_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rencontre_interclub ALTER lieu DROP NOT NULL');
        $this->addSql('ALTER TABLE rencontre_interclub ADD CONSTRAINT FK_125C5885F4F4DDD0 FOREIGN KEY (gymnase_id) REFERENCES gymnase (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_125C5885F4F4DDD0 ON rencontre_interclub (gymnase_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rencontre_interclub DROP CONSTRAINT FK_125C5885F4F4DDD0');
        $this->addSql('DROP INDEX IDX_125C5885F4F4DDD0');
        $this->addSql('ALTER TABLE rencontre_interclub DROP gymnase_id');
        $this->addSql('ALTER TABLE rencontre_interclub ALTER lieu SET NOT NULL');
    }
}
