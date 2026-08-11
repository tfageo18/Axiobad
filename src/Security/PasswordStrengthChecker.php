<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie qu'un mot de passe respecte des règles minimales de complexité et n'apparaît pas dans
 * une base de fuites de données connues (API « Have I Been Pwned », interrogée en k-anonymity :
 * seuls les 5 premiers caractères du hash SHA-1 du mot de passe sont envoyés, jamais le mot de
 * passe ni son hash complet).
 *
 * Si l'API est injoignable (panne, réseau bloqué...), la vérification de fuite est ignorée
 * (fail-open) : elle ne doit jamais empêcher un licencié de se connecter ou de changer de mot de
 * passe. Voir aussi PasswordExposureListener, qui revérifie périodiquement le mot de passe à la
 * connexion pour rattraper les cas de fail-open ou une fuite survenue après coup.
 */
class PasswordStrengthChecker
{
    private const LONGUEUR_MINIMALE = 8;
    private const API_URL = 'https://api.pwnedpasswords.com/range/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Retourne un message d'erreur si le mot de passe est invalide, ou null s'il est accepté.
     *
     * @param bool|null $expose Rempli en sortie avec le résultat de la vérification de fuite :
     *                          true si le mot de passe est compromis (auquel cas un message
     *                          d'erreur est aussi retourné), false s'il est vérifié comme sain,
     *                          null si la vérification n'a pas pu être faite (fail-open)
     */
    public function verifier(string $motDePasse, ?bool &$expose = null): ?string
    {
        $expose = null;

        if (strlen($motDePasse) < self::LONGUEUR_MINIMALE) {
            return sprintf('Le mot de passe doit contenir au moins %d caractères.', self::LONGUEUR_MINIMALE);
        }

        if (!preg_match('/[a-z]/', $motDePasse) || !preg_match('/[A-Z]/', $motDePasse) || !preg_match('/\d/', $motDePasse)) {
            return 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.';
        }

        $expose = $this->estExpose($motDePasse);
        if (true === $expose) {
            return "Ce mot de passe a été trouvé dans une base de données de fuites connues. Choisissez-en un autre, propre à ce compte.";
        }

        return null;
    }

    /**
     * Interroge l'API Have I Been Pwned en k-anonymity : seul le préfixe à 5 caractères du hash
     * SHA-1 du mot de passe est transmis, jamais le mot de passe ni son hash complet.
     *
     * @return bool|null true si compromis, false si sain, null si la vérification a échoué
     *                    (service injoignable) — à traiter comme « statut inconnu », pas comme
     *                    un mot de passe sain
     */
    public function estExpose(string $motDePasse): ?bool
    {
        $hash = strtoupper(sha1($motDePasse));
        $prefixe = substr($hash, 0, 5);
        $suffixe = substr($hash, 5);

        try {
            $response = $this->httpClient->request('GET', self::API_URL.$prefixe, [
                'timeout' => 3,
                'headers' => ['Add-Padding' => 'true'],
            ]);
            $corps = $response->getContent();
        } catch (HttpClientExceptionInterface $exception) {
            $this->logger->warning('Vérification Have I Been Pwned indisponible : {message}', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        foreach (explode("\r\n", trim($corps)) as $ligne) {
            [$suffixeConnu, ] = explode(':', $ligne, 2);
            if (0 === strcasecmp($suffixeConnu, $suffixe)) {
                return true;
            }
        }

        return false;
    }
}
