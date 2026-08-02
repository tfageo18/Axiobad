<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementRepository->findBy([], ['dateDebut' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $evenement = new Evenement();
            $this->hydrater($evenement, $request);

            $entityManager->persist($evenement);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé.');

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/form.html.twig', [
            'evenement' => null,
            'types' => Evenement::TYPES_LABELS,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_detail', methods: ['GET'])]
    public function detail(Evenement $evenement): Response
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        return $this->render('evenement/detail.html.twig', [
            'evenement' => $evenement,
            'monInscription' => $evenement->getInscriptionDe($user),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrater($evenement, $request);
            $entityManager->flush();

            $this->addFlash('success', 'Événement modifié.');

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/form.html.twig', [
            'evenement' => $evenement,
            'types' => Evenement::TYPES_LABELS,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_evenement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-evenement-'.$evenement->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}/inscription', name: 'app_evenement_inscription', methods: ['POST'])]
    public function inscrire(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $user */
        $user = $this->getUser();

        if ($evenement->getInscriptionDe($user)) {
            $this->addFlash('error', 'Vous êtes déjà inscrit(e) à cet événement.');

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        $inscription = (new Inscription())
            ->setEvenement($evenement)
            ->setLicencie($user)
            ->setStatut($evenement->aDesPlacesDisponibles() ? Inscription::STATUT_CONFIRMEE : Inscription::STATUT_LISTE_ATTENTE);

        $entityManager->persist($inscription);
        $entityManager->flush();

        $this->addFlash('success', Inscription::STATUT_CONFIRMEE === $inscription->getStatut()
            ? 'Inscription confirmée.'
            : 'Événement complet : vous êtes en liste d\'attente.');

        return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/desinscription/{inscriptionId}', name: 'app_evenement_desinscription', methods: ['POST'])]
    public function desinscrire(Request $request, Evenement $evenement, int $inscriptionId, EntityManagerInterface $entityManager): Response
    {
        $inscription = null;
        foreach ($evenement->getInscriptions() as $candidate) {
            if ($candidate->getId() === $inscriptionId) {
                $inscription = $candidate;

                break;
            }
        }

        if (!$inscription) {
            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        $estProprietaire = $inscription->getLicencie() === $this->getUser();
        if (!$estProprietaire && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('desinscription-'.$inscription->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        $etaitConfirmee = Inscription::STATUT_CONFIRMEE === $inscription->getStatut();
        $entityManager->remove($inscription);
        $entityManager->flush();

        if ($etaitConfirmee) {
            $premierEnAttente = null;
            foreach ($evenement->getListeAttente() as $candidat) {
                if (!$premierEnAttente || $candidat->getDateInscription() < $premierEnAttente->getDateInscription()) {
                    $premierEnAttente = $candidat;
                }
            }

            if ($premierEnAttente) {
                $premierEnAttente->setStatut(Inscription::STATUT_CONFIRMEE);
                $entityManager->flush();
            }
        }

        $this->addFlash('success', 'Désinscription enregistrée.');

        return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
    }

    private function hydrater(Evenement $evenement, Request $request): void
    {
        $dateDebut = (string) $request->request->get('dateDebut');
        $dateFin = (string) $request->request->get('dateFin');
        $nombrePlaces = $request->request->get('nombrePlaces');

        $evenement
            ->setType((string) $request->request->get('type'))
            ->setTitre((string) $request->request->get('titre'))
            ->setDescription((string) $request->request->get('description') ?: null)
            ->setLieu((string) $request->request->get('lieu') ?: null)
            ->setDateDebut(new \DateTimeImmutable($dateDebut))
            ->setDateFin($dateFin ? new \DateTimeImmutable($dateFin) : null)
            ->setNombrePlaces(null !== $nombrePlaces && '' !== $nombrePlaces ? (int) $nombrePlaces : null);
    }
}
