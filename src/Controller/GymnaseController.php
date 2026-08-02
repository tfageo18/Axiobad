<?php

namespace App\Controller;

use App\Entity\Gymnase;
use App\Repository\GymnaseRepository;
use App\Repository\LicencieRepository;
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
    public function new(Request $request, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        if ($request->isMethod('POST')) {
            $gymnase = (new Gymnase())
                ->setNom((string) $request->request->get('nom'))
                ->setAdresse((string) $request->request->get('adresse'))
                ->setTelephone((string) $request->request->get('telephone') ?: null);

            $this->appliquerPorteursCles($request, $gymnase, $licencieRepository);

            $entityManager->persist($gymnase);
            $entityManager->flush();

            $this->addFlash('success', 'Gymnase créé.');

            return $this->redirectToRoute('app_gymnase_index');
        }

        return $this->render('gymnase/form.html.twig', [
            'gymnase' => null,
            'licencies' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_gymnase_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function edit(Request $request, Gymnase $gymnase, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        if ($request->isMethod('POST')) {
            $gymnase
                ->setNom((string) $request->request->get('nom'))
                ->setAdresse((string) $request->request->get('adresse'))
                ->setTelephone((string) $request->request->get('telephone') ?: null);

            $this->appliquerPorteursCles($request, $gymnase, $licencieRepository);

            $entityManager->flush();

            $this->addFlash('success', 'Gymnase modifié.');

            return $this->redirectToRoute('app_gymnase_index');
        }

        return $this->render('gymnase/form.html.twig', [
            'gymnase' => $gymnase,
            'licencies' => $licencieRepository->findAll(),
        ]);
    }

    private function appliquerPorteursCles(Request $request, Gymnase $gymnase, LicencieRepository $licencieRepository): void
    {
        $idsSelectionnes = array_map('intval', $request->request->all('porteursCles'));

        foreach ($gymnase->getPorteursCles() as $licencie) {
            if (!in_array($licencie->getId(), $idsSelectionnes, true)) {
                $gymnase->removePorteurCles($licencie);
            }
        }

        foreach ($idsSelectionnes as $id) {
            $licencie = $licencieRepository->find($id);
            if ($licencie) {
                $gymnase->addPorteurCles($licencie);
            }
        }
    }

    #[Route('/{id}/activer', name: 'app_gymnase_toggle_actif', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function toggleActif(Gymnase $gymnase, EntityManagerInterface $entityManager): Response
    {
        $gymnase->setActif(!$gymnase->isActif());
        $entityManager->flush();

        $this->addFlash('success', $gymnase->isActif() ? 'Gymnase réactivé.' : 'Gymnase désactivé.');

        return $this->redirectToRoute('app_gymnase_index');
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
