<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Entity\Raquette;
use App\Repository\DemandeCordageRepository;
use App\Repository\RaquetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-raquettes')]
class RaquetteController extends AbstractController
{
    #[Route('', name: 'app_raquette_index', methods: ['GET'])]
    public function index(RaquetteRepository $raquetteRepository): Response
    {
        return $this->render('raquette/index.html.twig', [
            'raquettes' => $raquetteRepository->findBy(['licencie' => $this->getUser()]),
        ]);
    }

    #[Route('/nouveau', name: 'app_raquette_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            /** @var Licencie $licencie */
            $licencie = $this->getUser();

            $raquette = (new Raquette())
                ->setLicencie($licencie)
                ->setMarque((string) $request->request->get('marque'))
                ->setModele((string) $request->request->get('modele'))
                ->setSigneDistinctif((string) $request->request->get('signeDistinctif') ?: null)
                ->setTensionHabituelle((string) $request->request->get('tensionHabituelle') ?: null);

            $entityManager->persist($raquette);
            $entityManager->flush();

            $this->addFlash('success', 'Raquette ajoutée.');

            return $this->redirectToRoute('app_raquette_index');
        }

        return $this->render('raquette/form.html.twig', [
            'raquette' => null,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_raquette_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Raquette $raquette, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasProprietaire($raquette);

        if ($request->isMethod('POST')) {
            $raquette
                ->setMarque((string) $request->request->get('marque'))
                ->setModele((string) $request->request->get('modele'))
                ->setSigneDistinctif((string) $request->request->get('signeDistinctif') ?: null)
                ->setTensionHabituelle((string) $request->request->get('tensionHabituelle') ?: null);

            $entityManager->flush();

            $this->addFlash('success', 'Raquette modifiée.');

            return $this->redirectToRoute('app_raquette_index');
        }

        return $this->render('raquette/form.html.twig', [
            'raquette' => $raquette,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_raquette_delete', methods: ['POST'])]
    public function delete(Request $request, Raquette $raquette, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasProprietaire($raquette);

        if ($this->isCsrfTokenValid('delete-raquette-'.$raquette->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($raquette);
            $entityManager->flush();
            $this->addFlash('success', 'Raquette supprimée.');
        }

        return $this->redirectToRoute('app_raquette_index');
    }

    #[Route('/{id}/historique', name: 'app_raquette_historique', methods: ['GET'])]
    public function historique(Raquette $raquette, DemandeCordageRepository $demandeCordageRepository): Response
    {
        $this->refuserSiPasProprietaire($raquette);

        return $this->render('raquette/historique.html.twig', [
            'raquette' => $raquette,
            'demandes' => $demandeCordageRepository->findBy(['raquette' => $raquette], ['dateDepot' => 'DESC']),
        ]);
    }

    private function refuserSiPasProprietaire(Raquette $raquette): void
    {
        if ($raquette->getLicencie() !== $this->getUser() && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }
    }
}
