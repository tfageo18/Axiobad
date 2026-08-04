<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Licencie;
use App\Repository\AdhesionRepository;
use App\Repository\ConvocationRepository;
use App\Repository\DemandeCordageRepository;
use App\Repository\InscriptionRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Repository\RaquetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfilController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_mon_profil', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        LicencieRepository $licencieRepository,
        SluggerInterface $slugger,
    ): Response {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email');
            $telephone = (string) $request->request->get('telephone');
            $dateNaissance = (string) $request->request->get('dateNaissance');
            $numeroLicence = (string) $request->request->get('numeroLicence');

            if ($email && $email !== $licencie->getEmail()) {
                $existant = $licencieRepository->findOneBy(['email' => $email]);
                if ($existant && $existant->getId() !== $licencie->getId()) {
                    $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');

                    return $this->redirectToRoute('app_mon_profil');
                }
                $licencie->setEmail($email);
            }

            $licencie->setTelephone($telephone ?: null);
            $licencie->setGenre((string) $request->request->get('genre') ?: null);
            $licencie->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null);
            $licencie->setNumeroLicence($numeroLicence ?: null);
            $licencie->setClassementSimple($this->normaliserClassement($request->request->get('classementSimple')));
            $licencie->setClassementDouble($this->normaliserClassement($request->request->get('classementDouble')));
            $licencie->setClassementMixte($this->normaliserClassement($request->request->get('classementMixte')));

            $photoFile = $request->files->get('photo');
            if ($photoFile && !$photoFile->isValid()) {
                $this->addFlash('error', sprintf(
                    "La photo n'a pas pu être envoyée (%s). Essayez une image plus légère (moins de 10 Mo).",
                    $photoFile->getErrorMessage()
                ));
                $photoFile = null;
            }
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = sprintf('licencie-%d-%s.%s', $licencie->getId(), $safeFilename, $photoFile->guessExtension());

                try {
                    $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/photos';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0755, true);
                    }
                    $photoFile->move($uploadsDir, $newFilename);
                    $licencie->setPhoto('/uploads/photos/'.$newFilename);
                } catch (FileException) {
                    $this->addFlash('error', "Erreur lors de l'envoi de la photo.");
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_mon_profil');
        }

        return $this->render('licencie/profil.html.twig', [
            'licencie' => $licencie,
        ]);
    }

    #[Route('/mon-compte/mes-donnees', name: 'app_mon_profil_export', methods: ['GET'])]
    public function exporterMesDonnees(
        PresenceRepository $presenceRepository,
        ConvocationRepository $convocationRepository,
        InscriptionRepository $inscriptionRepository,
        DemandeCordageRepository $demandeCordageRepository,
        RaquetteRepository $raquetteRepository,
        AdhesionRepository $adhesionRepository,
    ): JsonResponse {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        $donnees = [
            'profil' => [
                'prenom' => $licencie->getPrenom(),
                'nom' => $licencie->getNom(),
                'email' => $licencie->getEmail(),
                'telephone' => $licencie->getTelephone(),
                'dateNaissance' => $licencie->getDateNaissance()?->format('Y-m-d'),
                'genre' => $licencie->getGenre(),
                'numeroLicence' => $licencie->getNumeroLicence(),
                'classementSimple' => $licencie->getClassementSimple(),
                'classementDouble' => $licencie->getClassementDouble(),
                'classementMixte' => $licencie->getClassementMixte(),
                'roles' => $licencie->getRoles(),
            ],
            'mineur' => [
                'responsableLegal1' => $licencie->getResponsableLegal1()?->getNomComplet(),
                'responsableLegal2' => $licencie->getResponsableLegal2()?->getNomComplet(),
                'personnesAutoriseesRecuperation' => $licencie->getPersonnesAutoriseesRecuperation(),
                'contactUrgenceNom' => $licencie->getContactUrgenceNom(),
                'contactUrgenceTelephone' => $licencie->getContactUrgenceTelephone(),
                'autorisationSortieSeul' => $licencie->isAutorisationSortieSeul(),
                'droitImage' => $licencie->isDroitImage(),
                'informationsSante' => $licencie->getInformationsSante(),
                'consentementDonneesSante' => $licencie->isConsentementDonneesSante(),
            ],
            'presences' => array_map(
                static fn ($p) => [
                    'creneau' => $p->getCreneau()->getNom(),
                    'date' => $p->getDate()->format('Y-m-d'),
                    'present' => $p->isPresent(),
                ],
                $presenceRepository->findBy(['licencie' => $licencie])
            ),
            'convocationsInterclubs' => array_map(
                static fn ($c) => [
                    'journee' => $c->getRencontre()?->getJournee(),
                    'adversaire' => $c->getRencontre()?->getAdversaire(),
                    'present' => $c->isPresent(),
                ],
                $convocationRepository->findBy(['licencie' => $licencie])
            ),
            'inscriptionsEvenements' => array_map(
                static fn ($i) => [
                    'evenement' => $i->getEvenement()?->getTitre(),
                    'statut' => $i->getStatut(),
                ],
                $inscriptionRepository->findBy(['licencie' => $licencie])
            ),
            'raquettes' => array_map(
                static fn ($r) => [
                    'marque' => $r->getMarque(),
                    'modele' => $r->getModele(),
                    'tensionHabituelle' => $r->getTensionHabituelle(),
                ],
                $raquetteRepository->findBy(['licencie' => $licencie])
            ),
            'demandesCordage' => array_map(
                static fn ($d) => [
                    'statut' => $d->getStatut(),
                    'dateDepot' => $d->getDateDepot()->format('Y-m-d'),
                    'tension' => $d->getTension(),
                ],
                $demandeCordageRepository->findBy(['licencie' => $licencie])
            ),
            'adhesions' => array_map(
                static fn ($a) => [
                    'saison' => $a->getSaison()?->getLibelle(),
                    'statut' => $a->getStatut(),
                    'montantTotal' => $a->getMontantTotal(),
                    'paiements' => array_map(
                        static fn ($p) => ['date' => $p->getDate()->format('Y-m-d'), 'montant' => $p->getMontant(), 'moyen' => $p->getMoyen()],
                        $a->getPaiements()->toArray()
                    ),
                ],
                $adhesionRepository->findBy(['licencie' => $licencie])
            ),
        ];

        $response = new JsonResponse($donnees, 200, [], false);
        $response->headers->set('Content-Disposition', 'attachment; filename="mes-donnees-axiobad.json"');

        return $response;
    }

    #[Route('/mon-compte/demander-suppression', name: 'app_mon_profil_demander_suppression', methods: ['POST'])]
    public function demanderSuppression(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if (!$this->isCsrfTokenValid('demander-suppression', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_mon_profil');
        }

        if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
            $this->addFlash('error', 'Le compte administrateur par défaut ne peut pas demander sa suppression.');

            return $this->redirectToRoute('app_mon_profil');
        }

        $licencie->setSuppressionDemandeeLe(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Votre demande de suppression de compte a été transmise au bureau, qui la traitera prochainement.');

        return $this->redirectToRoute('app_mon_profil');
    }

    private function normaliserClassement(mixed $valeur): ?string
    {
        $valeur = (string) $valeur;

        return in_array($valeur, ClassementFfbad::CODES, true) ? $valeur : null;
    }
}
