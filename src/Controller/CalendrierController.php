<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Repository\CreneauRepository;
use App\Repository\PresenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendrierController extends AbstractController
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    #[Route('/calendrier', name: 'app_calendrier', methods: ['GET'])]
    public function __invoke(Request $request, CreneauRepository $creneauRepository, PresenceRepository $presenceRepository): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $toutAfficher = $request->query->getBoolean('tous') || $this->isGranted('ROLE_BUREAU');

        $semaineParam = $request->query->get('semaine');
        $lundi = $semaineParam
            ? new \DateTimeImmutable($semaineParam)
            : new \DateTimeImmutable('monday this week');
        $lundi = $lundi->setTime(0, 0);

        $tousLesCreneaux = $creneauRepository->findAll();

        $jours = [];
        foreach (self::JOURS as $index => $nomJour) {
            $date = $lundi->modify(sprintf('+%d days', $index));

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
            foreach ($creneauxDuJour as $creneau) {
                $presences[$creneau->getId()] = $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date);
            }

            $jours[] = [
                'nom' => $nomJour,
                'date' => $date,
                'creneaux' => $creneauxDuJour,
                'presences' => $presences,
            ];
        }

        return $this->render('calendrier/index.html.twig', [
            'jours' => $jours,
            'lundi' => $lundi,
            'semainePrecedente' => $lundi->modify('-7 days')->format('Y-m-d'),
            'semaineSuivante' => $lundi->modify('+7 days')->format('Y-m-d'),
            'toutAfficher' => $toutAfficher,
        ]);
    }
}
