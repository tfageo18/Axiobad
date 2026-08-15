<?php

namespace App\Badminton;

/**
 * Catégories d'âge officielles FFBaD, de la plus jeune à la plus âgée. Utilisées comme liste de
 * sélection dans la fiche licencié (champ manuel, mis à jour aussi par la synchronisation
 * MyFFBaD) — indicatif seulement, sans effet sur le statut légal de minorité (voir
 * Licencie::estMineur(), basé uniquement sur la date de naissance).
 */
final class CategorieAge
{
    public const CODES = [
        'MINIBAD' => 'Mini-Bad',
        'POUSSIN_1' => 'Poussin 1',
        'POUSSIN_2' => 'Poussin 2',
        'BENJAMIN_1' => 'Benjamin 1',
        'BENJAMIN_2' => 'Benjamin 2',
        'MINIME_1' => 'Minime 1',
        'MINIME_2' => 'Minime 2',
        'CADET_1' => 'Cadet 1',
        'CADET_2' => 'Cadet 2',
        'JUNIOR_1' => 'Junior 1',
        'JUNIOR_2' => 'Junior 2',
        'SENIOR' => 'Senior',
        'VETERAN_1' => 'Vétéran 1',
        'VETERAN_2' => 'Vétéran 2',
        'VETERAN_3' => 'Vétéran 3',
        'VETERAN_4' => 'Vétéran 4',
        'VETERAN_5' => 'Vétéran 5',
    ];

    /**
     * Convertit un libellé de catégorie tel que renvoyé par MyFFBaD (ex. "Minime 2", "Vétéran 3",
     * "Senior") vers un code de la liste ci-dessus. Retourne null si le libellé est absent ou ne
     * correspond à aucune catégorie connue.
     */
    public static function depuisLibelleFfbad(?string $libelle): ?string
    {
        if (null === $libelle || '' === trim($libelle)) {
            return null;
        }

        $normalise = self::normaliser($libelle);

        foreach (self::CODES as $code => $libelleConnu) {
            if (self::normaliser($libelleConnu) === $normalise) {
                return $code;
            }
        }

        // Cas particuliers observés côté FFBaD : "Mini Bad", "Veteran 1"...
        $alias = [
            'MINI BAD' => 'MINIBAD',
            'MINIBAD' => 'MINIBAD',
        ];

        return $alias[$normalise] ?? null;
    }

    private static function normaliser(string $texte): string
    {
        $translitere = iconv('UTF-8', 'ASCII//TRANSLIT', $texte) ?: $texte;

        return strtoupper(trim(preg_replace('/\s+/', ' ', $translitere) ?? $texte));
    }
}
