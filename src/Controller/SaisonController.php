<?php

namespace App\Controller;

use App\Entity\Saison;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/saisons')]
#[IsGranted('ROLE_BUREAU')]
class SaisonController extends AbstractController
{
    #[Route('', name: 'app_saison_index', methods: ['GET'])]
    public function index(SaisonRepository $saisonRepository): Response
    {
        return $this->render('saison/index.html.twig', [
            'saisons' => $saisonRepository->findAllTrieesParDate(),
        ]);
    }

    #[Route('/nouveau', name: 'app_saison_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaire($request, $entityManager, new Saison());
    }

    #[Route('/{id}/modifier', name: 'app_saison_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Saison $saison, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaire($request, $entityManager, $saison);
    }

    #[Route('/{id}/supprimer', name: 'app_saison_delete', methods: ['POST'])]
    public function delete(Request $request, Saison $saison, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-saison-'.$saison->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($saison);
            $entityManager->flush();
            $this->addFlash('success', 'Saison supprimée.');
        }

        return $this->redirectToRoute('app_saison_index');
    }

    private function formulaire(Request $request, EntityManagerInterface $entityManager, Saison $saison): Response
    {
        if ($request->isMethod('POST')) {
            $saison
                ->setLibelle((string) $request->request->get('libelle'))
                ->setDateDebut(new \DateTimeImmutable((string) $request->request->get('dateDebut')))
                ->setDateFin(new \DateTimeImmutable((string) $request->request->get('dateFin')));

            $entityManager->persist($saison);
            $entityManager->flush();

            $this->addFlash('success', 'Saison enregistrée.');

            return $this->redirectToRoute('app_saison_index');
        }

        return $this->render('saison/form.html.twig', [
            'saison' => $saison->getId() ? $saison : null,
        ]);
    }
}
