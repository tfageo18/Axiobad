<?php

namespace App\Badminton;

/**
 * Calcule un rang numérique à partir du nom et de la division d'une équipe (champs en texte
 * libre — le niveau se trouve tantôt dans le nom (« National 3 », « D1 », « Prenat »), tantôt
 * dans la division (« 3 - Poule 5 »), selon la façon dont le club a rempli sa fiche) afin de
 * trier du meilleur niveau au moins bon : national < prénational < régional < départemental, et
 * à catégorie égale le numéro le plus bas est le plus haut (N1 avant N2 avant N3).
 */
final class NiveauInterclub
{
    /**
     * Une entrée par catégorie, dans l'ordre où elles doivent être testées : le prénational doit
     * être détecté avant le national, car son abréviation (« Prenat », « PN ») contient les mêmes
     * lettres.
     */
    private const CATEGORIES = [
        50 => '/PRE\s*-?\s*NAT(IONAL(E)?)?|(?<![A-Z])PN(?![A-Z])/',
        0 => '/NATIONAL(E)?|(?<![A-Z])N(?=\s*\d)|^N$/',
        100 => '/REGIONAL(E)?|(?<![A-Z])R(?=\s*\d)|^R$/',
        200 => '/DEPARTEMENTAL(E)?|(?<![A-Z])D(?=\s*\d)|^D$/',
    ];

    public static function rang(?string $nom, ?string $division): int
    {
        $texte = self::normaliser(trim((string) $nom.' '.(string) $division));

        if ('' === $texte) {
            return \PHP_INT_MAX;
        }

        foreach (self::CATEGORIES as $base => $motif) {
            if (preg_match($motif, $texte, $matches, \PREG_OFFSET_CAPTURE)) {
                $reste = substr($texte, $matches[0][1] + strlen($matches[0][0]));
                $niveau = preg_match('/\d+/', $reste, $matchNiveau) ? (int) $matchNiveau[0] : 0;

                return $base + $niveau;
            }
        }

        // Format non reconnu : classé après les niveaux identifiés mais avant les équipes sans division.
        return \PHP_INT_MAX - 1;
    }

    private static function normaliser(string $texte): string
    {
        $accents = ['É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'À' => 'A', 'Â' => 'A', 'Ô' => 'O', 'Û' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Î' => 'I', 'Ï' => 'I', 'Ç' => 'C'];

        return strtr(mb_strtoupper(trim($texte), 'UTF-8'), $accents);
    }
}
