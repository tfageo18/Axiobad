<?php

namespace App\Controller;

use App\Repository\ParametresClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parametres')]
#[IsGranted('ROLE_BUREAU')]
class ParametresClubController extends AbstractController
{
    #[Route('', name: 'app_parametres_club', methods: ['GET', 'POST'])]
    public function index(Request $request, ParametresClubRepository $parametresClubRepository, EntityManagerInterface $entityManager): Response
    {
        $parametres = $parametresClubRepository->obtenir();

        if ($request->isMethod('POST')) {
            $parametres
                ->setNomClub((string) $request->request->get('nomClub') ?: null)
                ->setNomClubMyFfbad((string) $request->request->get('nomClubMyFfbad') ?: null);

            $entityManager->flush();

            $this->addFlash('success', 'Réglages enregistrés.');

            return $this->redirectToRoute('app_parametres_club');
        }

        return $this->render('parametres_club/index.html.twig', [
            'parametres' => $parametres,
        ]);
    }
}
