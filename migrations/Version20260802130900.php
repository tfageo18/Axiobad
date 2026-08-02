<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée le compte administrateur par défaut (email: admin@axiobad.local, mot de passe: admin),
 * qui doit être changé à la première connexion.
 */
final class Version20260802130900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le compte administrateur par défaut';
    }

    public function up(Schema $schema): void
    {
        $hashedPassword = password_hash('admin', PASSWORD_BCRYPT);

        $this->addSql(
            'INSERT INTO licencie (email, roles, password, prenom, nom, must_change_password) '
            .'VALUES (:email, :roles, :password, :prenom, :nom, true)',
            [
                'email' => 'admin@axiobad.local',
                'roles' => '["ROLE_BUREAU"]',
                'password' => $hashedPassword,
                'prenom' => 'Admin',
                'nom' => 'Axiobad',
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM licencie WHERE email = :email', ['email' => 'admin@axiobad.local']);
    }
}
