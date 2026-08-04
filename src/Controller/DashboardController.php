<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\DemandeCordage;
use App\Entity\Licencie;
use App\Entity\Presence;
use App\Repository\AdhesionRepository;
use App\Repository\CreneauOuvertureRepository;
use App\Repository\CreneauRepository;
use App\Repository\DemandeCordageRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Repository\StockMouvementVolantRepository;
use App\Repository\StockVetementRepository;
use App\Repository\StockVolantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tableau-de-bord')]
#[IsGranted('ROLE_BUREAU')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        AdhesionRepository $adhesionRepository,
        PresenceRepository $presenceRepository,
        CreneauRepository $creneauRepository,
        StockVetementRepository $vetementRepository,
        StockVolantRepository $volantRepository,
        StockMouvementVolantRepository $mouvementVolantRepository,
        DemandeCordageRepository $demandeCordageRepository,
        CreneauOuvertureRepository $creneauOuvertureRepository,
    ): Response {
        $licencies = array_values(array_filter(
            $licencieRepository->findAll(),
            static fn (Licencie $l) => Licencie::EMAIL_ADMIN_DEFAUT !== $l->getEmail()
        ));

        $repartitionGenre = ['HOMME' => 0, 'FEMME' => 0, 'NON_PRECISE' => 0];
        $repartitionAge = ['ADULTE' => 0, 'ENFANT' => 0, 'INCONNU' => 0];
        $repartitionClassement = [];

        foreach ($licencies as $licencie) {
            $genre = $licencie->getGenre() ?? 'NON_PRECISE';
            ++$repartitionGenre[$genre];

            $categorie = $licencie->getCategorie() ?? 'INCONNU';
            ++$repartitionAge[$categorie];

            $classement = $licencie->getMeilleurClassement();
            if ($classement) {
                $repartitionClassement[$classement] = ($repartitionClassement[$classement] ?? 0) + 1;
            }
        }

        $saisonEnCours = $saisonRepository->findEnCours();
        $adhesionsPayees = 0;
        $adhesionsTotal = 0;
        if ($saisonEnCours) {
            $adhesions = $adhesionRepository->findBy(['saison' => $saisonEnCours]);
            $adhesionsTotal = count($adhesions);
            $adhesionsPayees = count(array_filter($adhesions, static fn (Adhesion $a) => $a->isPayee()));
        }

        $comparaisonSaisons = [];
        foreach ($saisonRepository->findAllTrieesParDate() as $saison) {
            $adhesionsSaison = $adhesionRepository->findBy(['saison' => $saison]);
            $montantCollecte = 0.0;
            foreach ($adhesionsSaison as $adhesion) {
                $montantCollecte += $adhesion->getMontantPaye();
            }

            $comparaisonSaisons[] = [
                'saison' => $saison,
                'adhesionsTotal' => count($adhesionsSaison),
                'adhesionsPayees' => count(array_filter($adhesionsSaison, static fn (Adhesion $a) => $a->isPayee())),
                'montantCollecte' => $montantCollecte,
            ];
        }

        $debutMois = new \DateTimeImmutable('first day of this month');
        $finMois = new \DateTimeImmutable('first day of next month');

        $presencesDuMois = $presenceRepository->createQueryBuilder('p')
            ->andWhere('p.date >= :debut AND p.date < :fin')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->getQuery()
            ->getResult();

        $reponsesDuMois = count($presencesDuMois);
        $presentsDuMois = count(array_filter($presencesDuMois, static fn (Presence $p) => $p->isPresent()));

        $occupationParCreneau = [];
        foreach ($creneauRepository->findAll() as $creneau) {
            $reponsesCreneau = array_filter($presencesDuMois, static fn (Presence $p) => $p->getCreneau() === $creneau);
            $total = count($reponsesCreneau);
            if (0 === $total) {
                continue;
            }
            $presents = count(array_filter($reponsesCreneau, static fn (Presence $p) => $p->isPresent()));
            $occupationParCreneau[] = [
                'creneau' => $creneau,
                'taux' => round($presents / $total * 100),
                'presents' => $presents,
                'total' => $total,
            ];
        }
        usort($occupationParCreneau, static fn (array $a, array $b) => $b['taux'] <=> $a['taux']);

        $volantsConsommesMois = (int) $mouvementVolantRepository->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.quantite), 0)')
            ->andWhere('m.type = :type')
            ->andWhere('m.creeLe >= :debut AND m.creeLe < :fin')
            ->setParameter('type', 'SORTIE')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->getQuery()
            ->getSingleScalarResult();

        $volantsConsommesTotal = (int) $mouvementVolantRepository->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.quantite), 0)')
            ->andWhere('m.type = :type')
            ->setParameter('type', 'SORTIE')
            ->getQuery()
            ->getSingleScalarResult();

        $valeurStock = 0.0;
        foreach ($vetementRepository->findAll() as $vetement) {
            $valeurStock += $vetement->getValeurStock();
        }
        foreach ($volantRepository->findAll() as $volant) {
            $valeurStock += $volant->getValeurStock();
        }

        $alertes = $this->construireAlertes(
            $licencies,
            $saisonEnCours,
            $adhesionRepository,
            $presenceRepository,
            $creneauRepository,
            $demandeCordageRepository,
            $creneauOuvertureRepository,
            $vetementRepository,
            $volantRepository
        );

        return $this->render('dashboard/index.html.twig', [
            'alertes' => $alertes,
            'nombreLicencies' => count($licencies),
            'repartitionGenre' => $repartitionGenre,
            'repartitionAge' => $repartitionAge,
            'repartitionClassement' => $repartitionClassement,
            'saisonEnCours' => $saisonEnCours,
            'adhesionsPayees' => $adhesionsPayees,
            'adhesionsTotal' => $adhesionsTotal,
            'reponsesDuMois' => $reponsesDuMois,
            'presentsDuMois' => $presentsDuMois,
            'occupationParCreneau' => $occupationParCreneau,
            'volantsConsommesMois' => $volantsConsommesMois,
            'volantsConsommesTotal' => $volantsConsommesTotal,
            'valeurStock' => $valeurStock,
            'comparaisonSaisons' => $comparaisonSaisons,
        ]);
    }

    /**
     * @param Licencie[] $licencies
     *
     * @return array<int, array{message: string, url: string, gravite: string}>
     */
    private function construireAlertes(
        array $licencies,
        ?\App\Entity\Saison $saisonEnCours,
        AdhesionRepository $adhesionRepository,
        PresenceRepository $presenceRepository,
        CreneauRepository $creneauRepository,
        DemandeCordageRepository $demandeCordageRepository,
        CreneauOuvertureRepository $creneauOuvertureRepository,
        StockVetementRepository $vetementRepository,
        StockVolantRepository $volantRepository,
    ): array {
        $alertes = [];
        $maintenant = new \DateTimeImmutable();

        if ($saisonEnCours) {
            $impayes = array_filter(
                $adhesionRepository->findBy(['saison' => $saisonEnCours]),
                static fn (Adhesion $a) => !$a->isPayee()
            );
            if (count($impayes) > 0) {
                $alertes[] = [
                    'message' => sprintf('%d adhésion(s) impayée(s) ou en attente cette saison', count($impayes)),
                    'url' => $this->generateUrl('app_licencie_index'),
                    'gravite' => 'warning',
                ];
            }
        }

        $demandesSuppression = array_filter($licencies, static fn (Licencie $l) => null !== $l->getSuppressionDemandeeLe());
        if (count($demandesSuppression) > 0) {
            $alertes[] = [
                'message' => sprintf('%d demande(s) de suppression de compte à traiter', count($demandesSuppression)),
                'url' => $this->generateUrl('app_licencie_index'),
                'gravite' => 'error',
            ];
        }

        $invitationsExpirees = array_filter(
            $licencies,
            static fn (Licencie $l) => !$l->aUnCompte() && null !== $l->getEmail() && null !== $l->getActivationTokenExpiresAt() && $l->getActivationTokenExpiresAt() < $maintenant
        );
        if (count($invitationsExpirees) > 0) {
            $alertes[] = [
                'message' => sprintf("%d invitation(s) expirée(s) sans compte activé", count($invitationsExpirees)),
                'url' => $this->generateUrl('app_licencie_index'),
                'gravite' => 'warning',
            ];
        }

        $promotionsEnAttente = array_filter(
            $presenceRepository->findAll(),
            static fn (Presence $p) => $p->estEnAttenteConfirmation()
        );
        if (count($promotionsEnAttente) > 0) {
            $alertes[] = [
                'message' => sprintf('%d promotion(s) de liste d\'attente en attente de confirmation', count($promotionsEnAttente)),
                'url' => $this->generateUrl('app_creneau_index'),
                'gravite' => 'warning',
            ];
        }

        $cordagesPretsDepuisLongtemps = array_filter(
            $demandeCordageRepository->findBy(['statut' => DemandeCordage::STATUT_PRETE]),
            static fn (DemandeCordage $d) => $d->getDatePrete() && $d->getDatePrete() < $maintenant->modify('-7 days')
        );
        if (count($cordagesPretsDepuisLongtemps) > 0) {
            $alertes[] = [
                'message' => sprintf('%d cordage(s) prêt(s) depuis plus de 7 jours, non récupéré(s)', count($cordagesPretsDepuisLongtemps)),
                'url' => $this->generateUrl('app_cordage_index'),
                'gravite' => 'warning',
            ];
        }

        $santeSansConsentement = array_filter(
            $licencies,
            static fn (Licencie $l) => $l->getInformationsSante() && !$l->isConsentementDonneesSante()
        );
        if (count($santeSansConsentement) > 0) {
            $alertes[] = [
                'message' => sprintf('%d fiche(s) avec information de santé sans consentement valide', count($santeSansConsentement)),
                'url' => $this->generateUrl('app_licencie_index'),
                'gravite' => 'error',
            ];
        }

        $mineursSansResponsable = array_filter(
            $licencies,
            static fn (Licencie $l) => !$l->aUnCompte() && 0 === count($l->getResponsablesLegaux())
        );
        if (count($mineursSansResponsable) > 0) {
            $alertes[] = [
                'message' => sprintf('%d licencié(s) sans compte et sans responsable légal renseigné', count($mineursSansResponsable)),
                'url' => $this->generateUrl('app_licencie_index'),
                'gravite' => 'warning',
            ];
        }

        $dansDeuxJours = $maintenant->modify('+2 days');
        foreach ($creneauRepository->findAll() as $creneau) {
            if (!$creneau->isActif()) {
                continue;
            }
            for ($date = new \DateTimeImmutable('today'); $date <= $dansDeuxJours; $date = $date->modify('+1 day')) {
                if (self::nomJourFrancais($date) !== $creneau->getJourSemaine()) {
                    continue;
                }
                $ouverture = $creneauOuvertureRepository->findOneByCreneauEtDate($creneau, $date);
                if (!$ouverture || !$ouverture->getLicencieOuverture()) {
                    $alertes[] = [
                        'message' => sprintf("Pas d'ouvreur assigné pour « %s » le %s", $creneau->getNom(), $date->format('d/m')),
                        'url' => $this->generateUrl('app_calendrier'),
                        'gravite' => 'warning',
                    ];
                }
            }
        }

        $articlesSousLeSeuil = array_merge(
            array_values(array_filter($vetementRepository->findAll(), static fn ($v) => $v->estSousLeSeuil())),
            array_values(array_filter($volantRepository->findAll(), static fn ($v) => $v->estSousLeSeuil()))
        );
        if (count($articlesSousLeSeuil) > 0) {
            $alertes[] = [
                'message' => sprintf('%d article(s) de stock sous le seuil d\'alerte', count($articlesSousLeSeuil)),
                'url' => $this->generateUrl('app_stock_index'),
                'gravite' => 'warning',
            ];
        }

        return $alertes;
    }

    private static function nomJourFrancais(\DateTimeImmutable $date): string
    {
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        return $jours[((int) $date->format('N')) - 1];
    }
}
