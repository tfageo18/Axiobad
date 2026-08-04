<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Entity\Evenement;
use App\Entity\Licencie;
use App\Repository\AdhesionRepository;
use App\Repository\ConvocationRepository;
use App\Repository\CreneauExceptionRepository;
use App\Repository\CreneauRepository;
use App\Repository\EvenementRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MonEspaceController extends AbstractController
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    #[Route('/mon-espace', name: 'app_mon_espace', methods: ['GET'])]
    public function __invoke(
        CreneauRepository $creneauRepository,
        PresenceRepository $presenceRepository,
        SaisonRepository $saisonRepository,
        AdhesionRepository $adhesionRepository,
        EvenementRepository $evenementRepository,
        ConvocationRepository $convocationRepository,
        CreneauExceptionRepository $exceptionRepository,
    ): Response {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        $creneauxActifs = array_values(array_filter($creneauRepository->findAll(), static fn (Creneau $c) => $c->isActif()));
        $creneauxCorrespondants = array_values(array_filter($creneauxActifs, static fn (Creneau $c) => $c->correspondA($licencie)));

        // Prochaines occurrences des créneaux qui me correspondent, sur les 14 prochains jours.
        $prochainsCreneaux = [];
        $aujourdhui = new \DateTimeImmutable('today');
        for ($i = 0; $i < 14; ++$i) {
            $date = $aujourdhui->modify(sprintf('+%d days', $i));
            $nomJour = self::JOURS[((int) $date->format('N')) - 1];

            foreach ($creneauxCorrespondants as $creneau) {
                if ($creneau->getJourSemaine() !== $nomJour) {
                    continue;
                }
                if ($creneau->getRecurrenceDebut() && $date < $creneau->getRecurrenceDebut()) {
                    continue;
                }
                if ($creneau->getRecurrenceFin() && $date > $creneau->getRecurrenceFin()) {
                    continue;
                }

                $exception = $exceptionRepository->findOneByCreneauEtDate($creneau, $date);
                if ($exception && $exception->estAnnulee()) {
                    continue;
                }

                $prochainsCreneaux[] = [
                    'creneau' => $creneau,
                    'date' => $date,
                    'exception' => $exception,
                    'presence' => $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date),
                ];
            }
        }
        usort($prochainsCreneaux, static fn (array $a, array $b) => $a['date'] <=> $b['date']);
        $prochainsCreneaux = array_slice($prochainsCreneaux, 0, 8);

        // Statistiques de présence globales.
        $presences = $presenceRepository->findBy(['licencie' => $licencie]);
        $totalReponses = count($presences);
        $totalPresent = count(array_filter($presences, static fn ($p) => $p->isPresent()));

        // Adhésion et paiements sur la saison en cours.
        $saisonEnCours = $saisonRepository->findEnCours();
        $adhesion = $saisonEnCours ? $adhesionRepository->findOneByLicencieEtSaison($licencie, $saisonEnCours) : null;

        // Évènements à venir.
        $evenementsAVenir = array_values(array_filter(
            $evenementRepository->findBy([], ['dateDebut' => 'ASC']),
            static fn (Evenement $e) => $e->getDateDebut() >= $aujourdhui
        ));
        $evenementsAVenir = array_slice($evenementsAVenir, 0, 5);

        // Historique des tournois internes (inscriptions passées) et des interclubs (convocations).
        $historiqueTournois = array_values(array_filter(
            $evenementRepository->findBy(['type' => Evenement::TYPE_TOURNOI_INTERNE], ['dateDebut' => 'DESC']),
            static fn (Evenement $e) => null !== $e->getInscriptionDe($licencie)
        ));

        $historiqueInterclubs = array_values(array_filter(
            $convocationRepository->findBy(['licencie' => $licencie]),
            static fn ($c) => $c->getRencontre()->getDateRencontre() < $aujourdhui
        ));
        usort($historiqueInterclubs, static fn ($a, $b) => $b->getRencontre()->getDateRencontre() <=> $a->getRencontre()->getDateRencontre());

        return $this->render('mon_espace/index.html.twig', [
            'prochainsCreneaux' => $prochainsCreneaux,
            'creneauxRecommandes' => $creneauxCorrespondants,
            'totalReponses' => $totalReponses,
            'totalPresent' => $totalPresent,
            'tauxPresence' => $totalReponses > 0 ? round($totalPresent / $totalReponses * 100) : null,
            'saisonEnCours' => $saisonEnCours,
            'adhesion' => $adhesion,
            'evenementsAVenir' => $evenementsAVenir,
            'historiqueTournois' => $historiqueTournois,
            'historiqueInterclubs' => $historiqueInterclubs,
        ]);
    }
}
