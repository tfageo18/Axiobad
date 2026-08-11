<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811150214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD email_auth_enabled BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN email_auth_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD email_auth_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE licencie ADD totp_auth_enabled BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE licencie ALTER COLUMN totp_auth_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE licencie ADD totp_secret VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "licencie" DROP email_auth_enabled');
        $this->addSql('ALTER TABLE "licencie" DROP email_auth_code');
        $this->addSql('ALTER TABLE "licencie" DROP totp_auth_enabled');
        $this->addSql('ALTER TABLE "licencie" DROP totp_secret');
    }
}
