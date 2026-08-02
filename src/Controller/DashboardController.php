<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Entity\Presence;
use App\Repository\AdhesionRepository;
use App\Repository\CreneauRepository;
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

        return $this->render('dashboard/index.html.twig', [
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
}
