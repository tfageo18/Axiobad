<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
use App\Service\FfbadClassementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/licencies')]
#[IsGranted('ROLE_BUREAU')]
class LicencieController extends AbstractController
{
    #[Route('', name: 'app_licencie_index', methods: ['GET'])]
    public function index(LicencieRepository $licencieRepository): Response
    {
        return $this->render('licencie/index.html.twig', [
            'licencies' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/classement', name: 'app_licencie_classement', methods: ['POST'])]
    public function classement(Licencie $licencie, FfbadClassementService $classementService, EntityManagerInterface $entityManager): Response
    {
        $classementService->mettreAJourClassement($licencie);
        $entityManager->flush();

        $this->addFlash('success', 'Classement mis à jour.');

        return $this->redirectToRoute('app_licencie_index');
    }
}
