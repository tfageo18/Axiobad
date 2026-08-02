<?php

namespace App\Badminton;

/**
 * Échelle officielle des classements FFBaD, du plus faible au plus fort.
 */
final class ClassementFfbad
{
    public const CODES = [
        'NC', 'P12', 'P11', 'P10', 'D9', 'D8', 'D7', 'R6', 'R5', 'R4', 'N3', 'N2', 'N1',
    ];

    /**
     * Position du classement dans l'échelle (0 = NC, plus haut = meilleur). -1 si absent/invalide.
     */
    public static function rang(?string $code): int
    {
        if (null === $code) {
            return -1;
        }

        $index = array_search($code, self::CODES, true);

        return false === $index ? -1 : $index;
    }
}
