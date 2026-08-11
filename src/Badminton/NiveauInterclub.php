<?php

namespace App\Badminton;

/**
 * Calcule un rang numérique à partir du champ « division » d'une équipe (texte libre, ex: N1, R2,
 * D3...) afin de trier les équipes et les rencontres du meilleur niveau au moins bon : national
 * avant régional avant départemental, et à catégorie égale le numéro le plus bas est le plus haut
 * (N1 avant N2 avant N3).
 */
final class NiveauInterclub
{
    private const BASES = [
        'N' => 0,
        'R' => 100,
        'D' => 200,
    ];

    public static function rang(?string $division): int
    {
        if (null === $division || '' === trim($division)) {
            return \PHP_INT_MAX;
        }

        $division = strtoupper(trim($division));

        if (preg_match('/^([NRD])\s*0*(\d+)/', $division, $matches)) {
            return self::BASES[$matches[1]] + (int) $matches[2];
        }

        if (preg_match('/^([NRD])/', $division, $matches)) {
            return self::BASES[$matches[1]];
        }

        // Format non reconnu : classé après les niveaux identifiés mais avant les équipes sans division.
        return \PHP_INT_MAX - 1;
    }
}
