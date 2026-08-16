<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\EvenementDocument;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Repository\EvenementDocumentRepository;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        $evenements = array_values(array_filter(
            $evenementRepository->findBy([], ['dateDebut' => 'ASC']),
            static fn (Evenement $e) => $e->estVisiblePar($user)
        ));

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenements,
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

        if (!$evenement->estVisiblePar($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('evenement/detail.html.twig', [
            'evenement' => $evenement,
            'monInscription' => $evenement->getInscriptionDe($user),
        ]);
    }

    #[Route('/{id}/documents', name: 'app_evenement_document_new', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function ajouterDocument(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('evenement-document-new-'.$evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        $fichier = $request->files->get('document');
        if (!$fichier) {
            $this->addFlash('error', 'Aucun fichier sélectionné.');

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }
        if (!$fichier->isValid()) {
            $this->addFlash('error', sprintf("Le document n'a pas pu être envoyé (%s).", $fichier->getErrorMessage()));

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        $nomOriginal = $fichier->getClientOriginalName();
        $nomSansExtension = pathinfo($nomOriginal, PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($nomSansExtension);
        $nomFichier = sprintf('%s-%s.%s', uniqid(), $safeFilename, $fichier->guessExtension() ?? 'bin');

        try {
            $uploadsDir = $this->getParameter('kernel.project_dir').'/var/uploads/evenements';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $fichier->move($uploadsDir, $nomFichier);
        } catch (FileException) {
            $this->addFlash('error', "Erreur lors de l'envoi du document.");

            return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
        }

        /** @var Licencie $utilisateur */
        $utilisateur = $this->getUser();

        $document = (new EvenementDocument())
            ->setEvenement($evenement)
            ->setNomOriginal($nomOriginal)
            ->setChemin($uploadsDir.'/'.$nomFichier)
            ->setAjoutePar($utilisateur);

        $entityManager->persist($document);
        $entityManager->flush();

        $this->addFlash('success', 'Document ajouté.');

        return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/documents/{documentId}/telecharger', name: 'app_evenement_document_telecharger', methods: ['GET'])]
    public function telechargerDocument(Evenement $evenement, int $documentId, EvenementDocumentRepository $evenementDocumentRepository): BinaryFileResponse
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        if (!$evenement->estVisiblePar($user)) {
            throw $this->createAccessDeniedException();
        }

        $document = $evenementDocumentRepository->find($documentId);
        if (!$document || $document->getEvenement() !== $evenement || !is_file($document->getChemin())) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($document->getChemin());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getNomOriginal());

        return $response;
    }

    #[Route('/{id}/documents/{documentId}/supprimer', name: 'app_evenement_document_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function supprimerDocument(Request $request, Evenement $evenement, int $documentId, EvenementDocumentRepository $evenementDocumentRepository, EntityManagerInterface $entityManager): Response
    {
        $document = $evenementDocumentRepository->find($documentId);
        if ($document && $document->getEvenement() === $evenement
            && $this->isCsrfTokenValid('evenement-document-delete-'.$document->getId(), (string) $request->request->get('_token'))
        ) {
            if (is_file($document->getChemin())) {
                @unlink($document->getChemin());
            }
            $entityManager->remove($document);
            $entityManager->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('app_evenement_detail', ['id' => $evenement->getId()]);
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

        if (!$evenement->estVisiblePar($user)) {
            throw $this->createAccessDeniedException();
        }

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
            ->setNombrePlaces(null !== $nombrePlaces && '' !== $nombrePlaces ? (int) $nombrePlaces : null)
            ->setVisibilite((string) $request->request->get('visibilite'));
    }
}
