<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802155734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE creneau ADD type VARCHAR(15) NOT NULL DEFAULT 'LOISIR'");
        $this->addSql('ALTER TABLE creneau ALTER type DROP DEFAULT');
        $this->addSql('ALTER TABLE creneau ADD ouvert_externes BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE creneau ALTER ouvert_externes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creneau DROP type');
        $this->addSql('ALTER TABLE creneau DROP ouvert_externes');
    }
}
