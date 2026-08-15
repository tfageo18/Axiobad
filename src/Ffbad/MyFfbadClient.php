<?php

namespace App\Ffbad;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client "au mieux" pour la page publique de recherche de joueurs de myffbad.fr (pas d'API
 * officielle documentée) : la page (Next.js) embarque les résultats en JSON dans le HTML rendu
 * côté serveur (balises `<script>self.__next_f.push(...)</script>`), pas besoin d'exécuter du
 * JavaScript pour les récupérer.
 *
 * Fragile par nature (dépend de la structure interne de myffbad.fr, qui peut changer sans
 * préavis) : toute erreur de parsing est absorbée et journalisée comme "aucun résultat" plutôt
 * que de faire planter la synchronisation.
 */
class MyFfbadClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Recherche un joueur précis par nom/prénom, dans le cadre du club dont l'URL d'effectif est
     * fournie (ligue/comité/club extraits de cette URL). Retourne le premier résultat s'il y en a
     * plusieurs (nom+prénom identiques dans le même club — rare).
     *
     * @return array{numeroLicence: string, nomComplet: string, classementSimple: ?string, classementDouble: ?string, classementMixte: ?string}|null
     */
    public function rechercherJoueur(string $urlEffectifClub, string $prenom, string $nom): ?array
    {
        $parametresClub = $this->extraireParametresClub($urlEffectifClub);
        if (!$parametresClub) {
            return null;
        }

        $url = 'https://myffbad.fr/recherche/joueur?'.http_build_query($parametresClub + [
            'nom' => $nom,
            'prenom' => $prenom,
            'isFirstLoad' => 'false',
        ]);

        $resultats = $this->recupererPage($url);

        return $resultats[0] ?? null;
    }

    /**
     * Récupère tout l'effectif du club (toutes les pages) depuis l'URL fournie par le bureau dans
     * les paramètres du club.
     *
     * @return list<array{numeroLicence: string, nomComplet: string, classementSimple: ?string, classementDouble: ?string, classementMixte: ?string}>
     */
    public function recupererEffectifComplet(string $urlEffectifClub): array
    {
        $parametresClub = $this->extraireParametresClub($urlEffectifClub);
        if (!$parametresClub) {
            return [];
        }

        $tous = [];
        $page = 1;
        $maxPages = 1;

        do {
            $url = 'https://myffbad.fr/recherche/joueur?'.http_build_query($parametresClub + [
                'isFirstLoad' => 'false',
                'page' => (string) $page,
            ]);

            [$resultats, $maxPagesTrouve] = $this->recupererPageAvecPagination($url);
            $tous = array_merge($tous, $resultats);
            $maxPages = $maxPagesTrouve ?? $maxPages;
            ++$page;
        } while ($page <= $maxPages && $page <= 50); // garde-fou, un club ne fait pas 50 pages

        return $tous;
    }

    /**
     * Compare un "PersonName" MyFFBaD (ex. "Suden ACAR", format "Prénom NOM") au prénom/nom
     * d'un licencié Axiobad, en ignorant casse, accents et espaces superflus.
     */
    public static function correspondNom(string $nomCompletMyFfbad, string $prenom, string $nom): bool
    {
        return self::normaliserPourComparaison($nomCompletMyFfbad) === self::normaliserPourComparaison($prenom.' '.$nom);
    }

    private static function normaliserPourComparaison(string $texte): string
    {
        $sansAccents = @iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
        $texte = false !== $sansAccents ? $sansAccents : $texte;

        return trim(preg_replace('/\s+/', ' ', mb_strtoupper($texte)));
    }

    /**
     * MyFFBaD affiche "-" pour un joueur non classé dans ce tableau — on ne veut pas l'écrire tel
     * quel dans nos classementSimple/Double/Mixte (l'échelle App\Badminton\ClassementFfbad ne le
     * connaît pas).
     */
    private function normaliserClassement(?string $classement): ?string
    {
        $classement = trim((string) $classement);

        return '' === $classement || '-' === $classement ? null : $classement;
    }

    /**
     * @return array{league?: string, committee?: string, club?: string}
     */
    private function extraireParametresClub(string $urlEffectifClub): array
    {
        $query = parse_url($urlEffectifClub, PHP_URL_QUERY);
        if (!$query) {
            return [];
        }

        parse_str($query, $params);

        $retenus = [];
        foreach (['league', 'committee', 'club'] as $cle) {
            if (!empty($params[$cle])) {
                $retenus[$cle] = (string) $params[$cle];
            }
        }

        return $retenus;
    }

    /**
     * @return list<array{numeroLicence: string, nomComplet: string, classementSimple: ?string, classementDouble: ?string, classementMixte: ?string}>
     */
    private function recupererPage(string $url): array
    {
        [$resultats] = $this->recupererPageAvecPagination($url);

        return $resultats;
    }

    /**
     * @return array{0: list<array{numeroLicence: string, nomComplet: string, classementSimple: ?string, classementDouble: ?string, classementMixte: ?string}>, 1: int|null}
     */
    private function recupererPageAvecPagination(string $url): array
    {
        try {
            $html = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Axiobad/1.0; +https://axiobad.click)'],
                'timeout' => 15,
            ])->getContent();
        } catch (\Throwable) {
            return [[], null];
        }

        $brut = $this->extraireResultatsBruts($html);
        $maxPages = $this->extraireMaxPages($html);

        $resultats = [];
        foreach ($brut as $joueur) {
            if (empty($joueur['PersonLicence']) || empty($joueur['PersonName'])) {
                continue;
            }
            $resultats[] = [
                'numeroLicence' => (string) $joueur['PersonLicence'],
                'nomComplet' => (string) $joueur['PersonName'],
                'classementSimple' => $this->normaliserClassement($joueur['SimpleSubLevel'] ?? null),
                'classementDouble' => $this->normaliserClassement($joueur['DoubleSubLevel'] ?? null),
                'classementMixte' => $this->normaliserClassement($joueur['MixteSubLevel'] ?? null),
            ];
        }

        return [$resultats, $maxPages];
    }

    /**
     * La page Next.js embarque son état initial dans des balises
     * <script>self.__next_f.push([N,"...JSON échappé..."])</script>. Chaque payload, une fois
     * déséchappé, contient quelque part un fragment `"results":[{...}, ...]` — c'est ce tableau
     * qu'on extrait, sans dépendre du reste de la structure (React Server Components) autour.
     *
     * @return list<array<string, mixed>>
     */
    private function extraireResultatsBruts(string $html): array
    {
        foreach ($this->extrairePayloadsNextJs($html) as $payload) {
            $resultats = $this->extraireTableauApresCle($payload, '"results":');
            if (null !== $resultats) {
                $decode = json_decode($resultats, true);
                if (is_array($decode)) {
                    return $decode;
                }
            }
        }

        return [];
    }

    private function extraireMaxPages(string $html): ?int
    {
        foreach ($this->extrairePayloadsNextJs($html) as $payload) {
            if (preg_match('/"maxPages":(\d+)/', $payload, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * @return list<string> le second élément (déséchappé) de chaque appel self.__next_f.push([N,"..."])
     */
    private function extrairePayloadsNextJs(string $html): array
    {
        $payloads = [];
        $offset = 0;
        while (false !== ($debut = strpos($html, 'self.__next_f.push(', $offset))) {
            $debutArgs = $debut + strlen('self.__next_f.push(');
            $fin = $this->trouverParentheseFermante($html, $debutArgs);
            if (null === $fin) {
                break;
            }

            $argsBruts = substr($html, $debutArgs, $fin - $debutArgs);
            $args = json_decode($argsBruts, true);
            if (is_array($args) && isset($args[1]) && is_string($args[1])) {
                $payloads[] = $args[1];
            }

            $offset = $fin + 1;
        }

        return $payloads;
    }

    /**
     * Trouve l'index de la ')' qui ferme la '(' commençant juste avant $debut, en respectant les
     * chaînes JSON (guillemets, échappements) pour ne pas se faire piéger par des parenthèses
     * présentes dans le texte des chaînes.
     */
    private function trouverParentheseFermante(string $texte, int $debut): ?int
    {
        $profondeur = 1; // on est déjà juste après la '(' d'ouverture
        $dansChaine = false;
        $echappe = false;
        $longueur = strlen($texte);

        for ($i = $debut; $i < $longueur; ++$i) {
            $car = $texte[$i];

            if ($dansChaine) {
                if ($echappe) {
                    $echappe = false;
                } elseif ('\\' === $car) {
                    $echappe = true;
                } elseif ('"' === $car) {
                    $dansChaine = false;
                }
                continue;
            }

            if ('"' === $car) {
                $dansChaine = true;
            } elseif ('(' === $car) {
                ++$profondeur;
            } elseif (')' === $car) {
                --$profondeur;
                if (0 === $profondeur) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Cherche $cle (ex. '"results":') dans $texte, puis extrait le tableau JSON `[...]` qui suit
     * immédiatement, en comptant les crochets (en respectant les chaînes) pour trouver sa fin.
     */
    private function extraireTableauApresCle(string $texte, string $cle): ?string
    {
        $position = strpos($texte, $cle);
        if (false === $position) {
            return null;
        }

        $debut = $position + strlen($cle);
        // Ignore les espaces éventuels avant le '['.
        while ($debut < strlen($texte) && ' ' === $texte[$debut]) {
            ++$debut;
        }
        if ($debut >= strlen($texte) || '[' !== $texte[$debut]) {
            return null;
        }

        $profondeur = 0;
        $dansChaine = false;
        $echappe = false;
        $longueur = strlen($texte);

        for ($i = $debut; $i < $longueur; ++$i) {
            $car = $texte[$i];

            if ($dansChaine) {
                if ($echappe) {
                    $echappe = false;
                } elseif ('\\' === $car) {
                    $echappe = true;
                } elseif ('"' === $car) {
                    $dansChaine = false;
                }
                continue;
            }

            if ('"' === $car) {
                $dansChaine = true;
            } elseif ('[' === $car) {
                ++$profondeur;
            } elseif (']' === $car) {
                --$profondeur;
                if (0 === $profondeur) {
                    return substr($texte, $debut, $i - $debut + 1);
                }
            }
        }

        return null;
    }
}
