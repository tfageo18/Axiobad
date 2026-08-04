<?php

namespace App\Controller;

use App\Entity\Equipe;
use App\Entity\Licencie;
use App\Repository\EquipeRepository;
use App\Repository\LicencieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/equipes')]
class EquipeController extends AbstractController
{
    #[Route('', name: 'app_equipe_index', methods: ['GET'])]
    public function index(EquipeRepository $equipeRepository): Response
    {
        return $this->render('equipe/index.html.twig', [
            'equipes' => $equipeRepository->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'app_equipe_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        if ($request->isMethod('POST')) {
            $equipe = (new Equipe())
                ->setNom((string) $request->request->get('nom'))
                ->setCategorie((string) $request->request->get('categorie') ?: null);

            $capitaine = $request->request->get('capitaine');
            if ($capitaine) {
                $equipe->setCapitaine($licencieRepository->find($capitaine));
            }

            $entityManager->persist($equipe);
            $entityManager->flush();

            $this->addFlash('success', 'Équipe créée. Vous pouvez maintenant y ajouter des membres.');

            return $this->redirectToRoute('app_equipe_edit', ['id' => $equipe->getId()]);
        }

        return $this->render('equipe/form.html.twig', [
            'equipe' => null,
            'licencies' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_equipe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipe $equipe, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $this->refuserSiPasAutorise($equipe);
        $estBureau = $this->isGranted('ROLE_BUREAU');

        if ($request->isMethod('POST')) {
            $equipe
                ->setNom((string) $request->request->get('nom'))
                ->setCategorie((string) $request->request->get('categorie') ?: null);

            if ($estBureau) {
                $capitaine = $request->request->get('capitaine');
                $equipe->setCapitaine($capitaine ? $licencieRepository->find($capitaine) : null);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Équipe modifiée.');

            return $this->redirectToRoute('app_equipe_edit', ['id' => $equipe->getId()]);
        }

        return $this->render('equipe/form.html.twig', [
            'equipe' => $equipe,
            'licencies' => $licencieRepository->findAll(),
            'estBureau' => $estBureau,
        ]);
    }

    #[Route('/{id}/membres', name: 'app_equipe_membre_new', methods: ['POST'])]
    public function ajouterMembre(Request $request, Equipe $equipe, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $this->refuserSiPasAutorise($equipe);

        $licencie = $licencieRepository->find($request->request->get('licencie'));
        if (!$licencie) {
            $this->addFlash('error', 'Licencié invalide.');

            return $this->redirectToRoute('app_equipe_edit', ['id' => $equipe->getId()]);
        }

        $equipe->addMembre($licencie);
        $entityManager->flush();

        $this->addFlash('success', 'Membre ajouté.');

        return $this->redirectToRoute('app_equipe_edit', ['id' => $equipe->getId()]);
    }

    #[Route('/{id}/membres/{membreId}/retirer', name: 'app_equipe_membre_delete', methods: ['POST'])]
    public function retirerMembre(Request $request, Equipe $equipe, int $membreId, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $this->refuserSiPasAutorise($equipe);

        if ($this->isCsrfTokenValid('retirer-membre-'.$membreId, (string) $request->request->get('_token'))) {
            $membre = $licencieRepository->find($membreId);
            if ($membre) {
                $equipe->removeMembre($membre);
                $entityManager->flush();
                $this->addFlash('success', 'Membre retiré.');
            }
        }

        return $this->redirectToRoute('app_equipe_edit', ['id' => $equipe->getId()]);
    }

    #[Route('/{id}/activer', name: 'app_equipe_toggle_actif', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function toggleActif(Equipe $equipe, EntityManagerInterface $entityManager): Response
    {
        $equipe->setActif(!$equipe->isActif());
        $entityManager->flush();

        $this->addFlash('success', $equipe->isActif() ? 'Équipe réactivée.' : 'Équipe désactivée.');

        return $this->redirectToRoute('app_equipe_index');
    }

    #[Route('/{id}/supprimer', name: 'app_equipe_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, Equipe $equipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-equipe-'.$equipe->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($equipe);
            $entityManager->flush();
            $this->addFlash('success', 'Équipe supprimée.');
        }

        return $this->redirectToRoute('app_equipe_index');
    }

    private function refuserSiPasAutorise(Equipe $equipe): void
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        if (!$this->isGranted('ROLE_BUREAU') && !$equipe->estCapitaine($user)) {
            throw $this->createAccessDeniedException();
        }
    }
}
