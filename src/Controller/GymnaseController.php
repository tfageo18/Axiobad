<?php

namespace App\Controller;

use App\Entity\Gymnase;
use App\Repository\GymnaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gymnases')]
class GymnaseController extends AbstractController
{
    #[Route('', name: 'app_gymnase_index', methods: ['GET'])]
    public function index(GymnaseRepository $gymnaseRepository): Response
    {
        return $this->render('gymnase/index.html.twig', [
            'gymnases' => $gymnaseRepository->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'app_gymnase_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $gymnase = (new Gymnase())
                ->setNom((string) $request->request->get('nom'))
                ->setAdresse((string) $request->request->get('adresse'));

            $entityManager->persist($gymnase);
            $entityManager->flush();

            $this->addFlash('success', 'Gymnase créé.');

            return $this->redirectToRoute('app_gymnase_index');
        }

        return $this->render('gymnase/new.html.twig');
    }

    #[Route('/{id}/supprimer', name: 'app_gymnase_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, Gymnase $gymnase, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-gymnase-'.$gymnase->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($gymnase);
            $entityManager->flush();
            $this->addFlash('success', 'Gymnase supprimé.');
        }

        return $this->redirectToRoute('app_gymnase_index');
    }
}
