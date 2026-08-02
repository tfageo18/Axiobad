<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802130806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE licencie ADD must_change_password BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE licencie ADD activation_token VARCHAR(100) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie
            ADD
              activation_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B755612B1B4826B ON licencie (activation_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_3B755612B1B4826B');
        $this->addSql('ALTER TABLE "licencie" DROP must_change_password');
        $this->addSql('ALTER TABLE "licencie" DROP activation_token');
        $this->addSql('ALTER TABLE "licencie" DROP activation_token_expires_at');
    }
}
