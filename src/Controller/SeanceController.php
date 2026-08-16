<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Seance;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contenu des séances dirigées : saisi par un des entraîneurs du créneau (ou le bureau), une
 * séance par occurrence (créneau + date). Publiable aux licenciés inscrits à cette occurrence.
 */
#[Route('/creneaux/{id}/seances/{date}', name: 'app_seance_')]
class SeanceController extends AbstractController
{
    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Creneau $creneau, string $date, SeanceRepository $seanceRepository, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $seance = $seanceRepository->findOneByCreneauEtDate($creneau, $dateObjet) ?? (new Seance())->setCreneau($creneau)->setDate($dateObjet);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('seance-edit-'.$creneau->getId().'-'.$date, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_seance_edit', ['id' => $creneau->getId(), 'date' => $date]);
            }

            /** @var Licencie $redacteur */
            $redacteur = $this->getUser();

            $seance
                ->setRedacteur($redacteur)
                ->setObjectifs((string) $request->request->get('objectifs') ?: null)
                ->setContenu((string) $request->request->get('contenu') ?: null)
                ->setPubliee((bool) $request->request->get('publiee'))
                ->toucher();

            $entityManager->persist($seance);
            $entityManager->flush();

            $this->addFlash('success', 'Séance enregistrée.');

            return $this->redirectToRoute('app_creneau_detail', ['id' => $creneau->getId(), 'date' => $date]);
        }

        $seancePrecedente = $seance->estVide() ? $seanceRepository->findDernierePourCreneau($creneau, $dateObjet) : null;

        return $this->render('seance/form.html.twig', [
            'creneau' => $creneau,
            'date' => $dateObjet,
            'seance' => $seance,
            'seancePrecedente' => $seancePrecedente,
        ]);
    }

    /**
     * Préremplit le formulaire (sans enregistrer) avec le contenu de la dernière séance
     * renseignée, pour aller plus vite plutôt que de tout retaper chaque semaine.
     */
    #[Route('/dupliquer', name: 'dupliquer', methods: ['GET'])]
    public function dupliquer(Creneau $creneau, string $date, SeanceRepository $seanceRepository): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $modele = $seanceRepository->findDernierePourCreneau($creneau, $dateObjet);

        $seance = (new Seance())->setCreneau($creneau)->setDate($dateObjet);
        if ($modele) {
            $seance->setObjectifs($modele->getObjectifs())->setContenu($modele->getContenu());
        }

        return $this->render('seance/form.html.twig', [
            'creneau' => $creneau,
            'date' => $dateObjet,
            'seance' => $seance,
            'seancePrecedente' => null,
        ]);
    }

    private function refuserSiPasEntraineur(Creneau $creneau): void
    {
        /** @var Licencie $utilisateur */
        $utilisateur = $this->getUser();

        if (!$creneau->isEncadre() || (!$creneau->estEncadrePar($utilisateur) && !$this->isGranted('ROLE_BUREAU'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
