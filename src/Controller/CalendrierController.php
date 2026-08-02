<?php

namespace App\Controller;

use App\Repository\CreneauRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendrierController extends AbstractController
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    #[Route('/calendrier', name: 'app_calendrier', methods: ['GET'])]
    public function __invoke(CreneauRepository $creneauRepository): Response
    {
        $creneaux = $creneauRepository->findAll();

        $parJour = [];
        foreach (self::JOURS as $jour) {
            $parJour[$jour] = [];
        }
        foreach ($creneaux as $creneau) {
            $parJour[$creneau->getJourSemaine()][] = $creneau;
        }

        foreach ($parJour as &$creneauxJour) {
            usort($creneauxJour, static fn ($a, $b) => $a->getHeureDebut() <=> $b->getHeureDebut());
        }
        unset($creneauxJour);

        return $this->render('calendrier/index.html.twig', [
            'jours' => self::JOURS,
            'parJour' => $parJour,
        ]);
    }
}
