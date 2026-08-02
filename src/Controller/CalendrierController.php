<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Repository\CreneauOuvertureRepository;
use App\Repository\CreneauRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendrierController extends AbstractController
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    private const MOIS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    #[Route('/calendrier', name: 'app_calendrier', methods: ['GET'])]
    public function __invoke(
        Request $request,
        CreneauRepository $creneauRepository,
        PresenceRepository $presenceRepository,
        CreneauOuvertureRepository $ouvertureRepository,
        LicencieRepository $licencieRepository,
    ): Response {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $toutAfficher = $request->query->getBoolean('tous') || $this->isGranted('ROLE_BUREAU');

        $moisParam = $request->query->get('mois');
        $premierDuMois = $moisParam
            ? new \DateTimeImmutable($moisParam.'-01')
            : new \DateTimeImmutable('first day of this month');
        $premierDuMois = $premierDuMois->setTime(0, 0);

        $premierJourGrille = $premierDuMois->modify(sprintf('-%d days', ((int) $premierDuMois->format('N')) - 1));
        $dernierDuMois = $premierDuMois->modify('last day of this month');
        $dernierJourGrille = $dernierDuMois->modify(sprintf('+%d days', 7 - (int) $dernierDuMois->format('N')));

        $tousLesCreneaux = $creneauRepository->findAll();

        $semaines = [];
        $semaineCourante = [];
        $date = $premierJourGrille;
        while ($date <= $dernierJourGrille) {
            $nomJour = self::JOURS[((int) $date->format('N')) - 1];

            $creneauxDuJour = array_values(array_filter($tousLesCreneaux, function (Creneau $c) use ($nomJour, $date, $toutAfficher, $licencie) {
                if ($c->getJourSemaine() !== $nomJour) {
                    return false;
                }
                if ($c->getRecurrenceDebut() && $date < $c->getRecurrenceDebut()) {
                    return false;
                }
                if ($c->getRecurrenceFin() && $date > $c->getRecurrenceFin()) {
                    return false;
                }

                return $toutAfficher || $c->correspondA($licencie);
            }));
            usort($creneauxDuJour, static fn (Creneau $a, Creneau $b) => $a->getHeureDebut() <=> $b->getHeureDebut());

            $presences = [];
            $ouvertures = [];
            foreach ($creneauxDuJour as $creneau) {
                $presences[$creneau->getId()] = $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date);
                $ouvertures[$creneau->getId()] = $ouvertureRepository->findOneByCreneauEtDate($creneau, $date);
            }

            $semaineCourante[] = [
                'date' => $date,
                'nom' => $nomJour,
                'dansLeMois' => $date->format('m') === $premierDuMois->format('m'),
                'creneaux' => $creneauxDuJour,
                'presences' => $presences,
                'ouvertures' => $ouvertures,
            ];

            if (7 === count($semaineCourante)) {
                $semaines[] = $semaineCourante;
                $semaineCourante = [];
            }

            $date = $date->modify('+1 day');
        }

        return $this->render('calendrier/index.html.twig', [
            'semaines' => $semaines,
            'premierDuMois' => $premierDuMois,
            'nomMois' => self::MOIS[(int) $premierDuMois->format('n')].' '.$premierDuMois->format('Y'),
            'moisPrecedent' => $premierDuMois->modify('-1 month')->format('Y-m'),
            'moisSuivant' => $premierDuMois->modify('+1 month')->format('Y-m'),
            'toutAfficher' => $toutAfficher,
            'licenciesDisponibles' => $this->isGranted('ROLE_BUREAU') ? $licencieRepository->findAll() : [],
        ]);
    }
}
