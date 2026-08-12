<?php

namespace App\Demo;

use App\Entity\Licencie;

/**
 * Comptes de démonstration affichés sur la page de connexion quand DEMO_MODE est activé
 * (instance demo.axiobad.click uniquement — voir SecurityController).
 *
 * Un seul point de vérité : la commande de seed (app:demo:reset) et la page de login
 * lisent toutes les deux cette liste, pour éviter que les identifiants affichés se
 * désynchronisent des comptes réellement créés en base.
 */
final class DemoAccounts
{
    /** Mot de passe commun à tous les comptes de démo (instance publique, données jetables). */
    public const PASSWORD = 'Demo-Axiobad-2026!';

    /**
     * @return list<array{email: string, prenom: string, nom: string, roles: string[], label: string}>
     */
    public static function all(): array
    {
        return [
            [
                'email' => 'demo.bureau@axiobad.click',
                'prenom' => 'Camille',
                'nom' => 'Bureau',
                'roles' => [Licencie::ROLE_BUREAU],
                'label' => 'Bureau (administration complète)',
            ],
            [
                'email' => 'demo.entraineur@axiobad.click',
                'prenom' => 'Karim',
                'nom' => 'Entraineur',
                'roles' => [Licencie::ROLE_ENTRAINEUR],
                'label' => 'Entraîneur',
            ],
            [
                'email' => 'demo.cordeur@axiobad.click',
                'prenom' => 'Sofia',
                'nom' => 'Cordeur',
                'roles' => [Licencie::ROLE_CORDEUR],
                'label' => 'Cordeur',
            ],
            [
                'email' => 'demo.stock@axiobad.click',
                'prenom' => 'Yanis',
                'nom' => 'Stock',
                'roles' => [Licencie::ROLE_STOCK],
                'label' => 'Gestion du stock',
            ],
            [
                'email' => 'demo.licencie@axiobad.click',
                'prenom' => 'Alice',
                'nom' => 'Licencie',
                'roles' => [],
                'label' => 'Licencié (espace joueur)',
            ],
        ];
    }
}
