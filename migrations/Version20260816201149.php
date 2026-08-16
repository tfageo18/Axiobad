<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816201149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le lien optionnel salarié -> licencié (un salarié peut aussi être licencié du club).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE salarie ADD licencie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE salarie ADD CONSTRAINT FK_828E3A1AB56DCD74 FOREIGN KEY (licencie_id) REFERENCES licencie (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_828E3A1AB56DCD74 ON salarie (licencie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE salarie DROP CONSTRAINT FK_828E3A1AB56DCD74');
        $this->addSql('DROP INDEX IDX_828E3A1AB56DCD74');
        $this->addSql('ALTER TABLE salarie DROP licencie_id');
    }
}
