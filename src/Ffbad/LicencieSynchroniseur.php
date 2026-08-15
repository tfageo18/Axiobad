<?php

namespace App\Ffbad;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
use App\Repository\ParametresClubRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée unique pour la synchronisation MyFFBaD, utilisé aussi bien par le bureau
 * (fiche licencié, synchro groupée) que par le licencié lui-même (Mon compte) — centralise la
 * mise à jour de myFfbadDerniereSyncLe/myFfbadSyncReussie pour que le statut affiché soit
 * cohérent partout.
 */
class LicencieSynchroniseur
{
    public const ERREUR_URL_NON_CONFIGUREE = 'url_non_configuree';
    public const ERREUR_AUCUNE_DONNEE = 'aucune_donnee';

    public function __construct(
        private readonly ParametresClubRepository $parametresClubRepository,
        private readonly LicencieRepository $licencieRepository,
        private readonly MyFfbadClient $myFfbadClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{trouve: bool, erreur: ?string, correspondance: ?array}
     */
    public function synchroniserUn(Licencie $licencie): array
    {
        $urlEffectif = $this->parametresClubRepository->obtenir()->getUrlEffectifMyFfbad();
        if (!$urlEffectif) {
            return ['trouve' => false, 'erreur' => self::ERREUR_URL_NON_CONFIGUREE, 'correspondance' => null];
        }

        $correspondance = $this->myFfbadClient->rechercherJoueur($urlEffectif, $licencie->getPrenom(), $licencie->getNom());

        $licencie->setMyFfbadDerniereSyncLe(new \DateTimeImmutable());
        $licencie->setMyFfbadSyncReussie(null !== $correspondance);

        if ($correspondance) {
            $this->appliquerCorrespondance($licencie, $correspondance);
        }

        $this->entityManager->flush();

        return ['trouve' => null !== $correspondance, 'erreur' => $correspondance ? null : 'aucune_correspondance', 'correspondance' => $correspondance];
    }

    /**
     * @return array{misAJour: int, nonTrouves: int, erreur: ?string}
     */
    public function synchroniserTous(): array
    {
        $urlEffectif = $this->parametresClubRepository->obtenir()->getUrlEffectifMyFfbad();
        if (!$urlEffectif) {
            return ['misAJour' => 0, 'nonTrouves' => 0, 'erreur' => self::ERREUR_URL_NON_CONFIGUREE];
        }

        $effectif = $this->myFfbadClient->recupererEffectifComplet($urlEffectif);
        if (!$effectif) {
            return ['misAJour' => 0, 'nonTrouves' => 0, 'erreur' => self::ERREUR_AUCUNE_DONNEE];
        }

        $maintenant = new \DateTimeImmutable();
        $misAJour = 0;
        $nonTrouves = 0;

        foreach ($this->licencieRepository->findAll() as $licencie) {
            $correspondance = null;
            foreach ($effectif as $joueur) {
                if (MyFfbadClient::correspondNom($joueur['nomComplet'], $licencie->getPrenom(), $licencie->getNom())) {
                    $correspondance = $joueur;
                    break;
                }
            }

            $licencie->setMyFfbadDerniereSyncLe($maintenant);
            $licencie->setMyFfbadSyncReussie(null !== $correspondance);

            if ($correspondance) {
                $this->appliquerCorrespondance($licencie, $correspondance);
                ++$misAJour;
            } else {
                ++$nonTrouves;
            }
        }

        $this->entityManager->flush();

        return ['misAJour' => $misAJour, 'nonTrouves' => $nonTrouves, 'erreur' => null];
    }

    /**
     * @param array{numeroLicence: string, nomComplet: string, classementSimple: ?string, classementDouble: ?string, classementMixte: ?string} $correspondance
     */
    private function appliquerCorrespondance(Licencie $licencie, array $correspondance): void
    {
        $licencie
            ->setNumeroLicence($correspondance['numeroLicence'])
            ->setClassementSimple($correspondance['classementSimple'])
            ->setClassementDouble($correspondance['classementDouble'])
            ->setClassementMixte($correspondance['classementMixte'])
            ->setClassementMisAJourLe(new \DateTimeImmutable());
    }
}
