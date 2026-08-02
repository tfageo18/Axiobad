<?php

namespace App\Controller;

use App\Entity\Convocation;
use App\Entity\Equipe;
use App\Entity\Licencie;
use App\Entity\RencontreInterclub;
use App\Repository\EquipeRepository;
use App\Repository\LicencieRepository;
use App\Repository\RencontreInterclubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/interclubs')]
class InterclubController extends AbstractController
{
    #[Route('', name: 'app_interclub_index', methods: ['GET'])]
    public function index(RencontreInterclubRepository $rencontreRepository): Response
    {
        return $this->render('interclub/index.html.twig', [
            'rencontres' => $rencontreRepository->findBy([], ['dateRencontre' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_interclub_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager, EquipeRepository $equipeRepository): Response
    {
        if ($request->isMethod('POST')) {
            $rencontre = new RencontreInterclub();
            $this->hydrater($rencontre, $request, $equipeRepository);

            $entityManager->persist($rencontre);
            $entityManager->flush();

            $this->addFlash('success', 'Rencontre créée.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        return $this->render('interclub/form.html.twig', [
            'rencontre' => null,
            'equipes' => $equipeRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_interclub_detail', methods: ['GET'])]
    public function detail(RencontreInterclub $rencontre, LicencieRepository $licencieRepository): Response
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        $convoques = array_map(static fn (Convocation $c) => $c->getLicencie(), $rencontre->getConvocations()->toArray());
        $membresConvocables = array_filter(
            $rencontre->getEquipe()->getMembres()->toArray(),
            static fn (Licencie $l) => !in_array($l, $convoques, true)
        );

        return $this->render('interclub/detail.html.twig', [
            'rencontre' => $rencontre,
            'maConvocation' => $rencontre->getConvocationDe($user),
            'membresConvocables' => $membresConvocables,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_interclub_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function edit(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager, EquipeRepository $equipeRepository): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrater($rencontre, $request, $equipeRepository);
            $entityManager->flush();

            $this->addFlash('success', 'Rencontre modifiée.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        return $this->render('interclub/form.html.twig', [
            'rencontre' => $rencontre,
            'equipes' => $equipeRepository->findAll(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_interclub_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-rencontre-'.$rencontre->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($rencontre);
            $entityManager->flush();
            $this->addFlash('success', 'Rencontre supprimée.');
        }

        return $this->redirectToRoute('app_interclub_index');
    }

    #[Route('/{id}/convocations', name: 'app_interclub_convoquer', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function convoquer(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $licencie = $licencieRepository->find($request->request->get('licencie'));
        if (!$licencie || !$rencontre->getEquipe()->getMembres()->contains($licencie) || $rencontre->getConvocationDe($licencie)) {
            $this->addFlash('error', 'Convocation impossible.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        $convocation = (new Convocation())
            ->setRencontre($rencontre)
            ->setLicencie($licencie);

        $entityManager->persist($convocation);
        $entityManager->flush();

        $this->addFlash('success', 'Joueur convoqué.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/convocations/{convocationId}/retirer', name: 'app_interclub_retirer_convocation', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function retirerConvocation(Request $request, RencontreInterclub $rencontre, int $convocationId, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('retirer-convocation-'.$convocationId, (string) $request->request->get('_token'))) {
            foreach ($rencontre->getConvocations() as $convocation) {
                if ($convocation->getId() === $convocationId) {
                    $entityManager->remove($convocation);
                    $entityManager->flush();
                    $this->addFlash('success', 'Convocation retirée.');

                    break;
                }
            }
        }

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/convocations/{convocationId}/repondre', name: 'app_interclub_repondre', methods: ['POST'])]
    public function repondre(Request $request, RencontreInterclub $rencontre, int $convocationId, EntityManagerInterface $entityManager): Response
    {
        $convocation = null;
        foreach ($rencontre->getConvocations() as $candidate) {
            if ($candidate->getId() === $convocationId) {
                $convocation = $candidate;

                break;
            }
        }

        if (!$convocation) {
            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        if ($convocation->getLicencie() !== $this->getUser() && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }

        $convocation->setPresent('1' === $request->request->get('present'));
        $entityManager->flush();

        $this->addFlash('success', 'Réponse enregistrée.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    private function hydrater(RencontreInterclub $rencontre, Request $request, EquipeRepository $equipeRepository): void
    {
        $equipe = $equipeRepository->find($request->request->get('equipe'));
        $scoreEquipe = $request->request->get('scoreEquipe');
        $scoreAdversaire = $request->request->get('scoreAdversaire');

        $rencontre
            ->setEquipe($equipe instanceof Equipe ? $equipe : null)
            ->setJournee((int) $request->request->get('journee'))
            ->setDateRencontre(new \DateTimeImmutable((string) $request->request->get('dateRencontre')))
            ->setLieu((string) $request->request->get('lieu'))
            ->setAdversaire((string) $request->request->get('adversaire'))
            ->setScoreEquipe(null !== $scoreEquipe && '' !== $scoreEquipe ? (int) $scoreEquipe : null)
            ->setScoreAdversaire(null !== $scoreAdversaire && '' !== $scoreAdversaire ? (int) $scoreAdversaire : null);
    }
}
