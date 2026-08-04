<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Adhesion;
use App\Entity\CleGymnase;
use App\Entity\Convocation;
use App\Entity\DemandeCordage;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Entity\PaiementAdhesion;
use App\Entity\Presence;
use App\Entity\Raquette;
use App\Entity\StockMouvementVetement;
use App\Entity\StockMouvementVolant;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\PaiementAdhesionRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Service\InvitationMailer;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/licencies')]
#[IsGranted('ROLE_BUREAU')]
class LicencieController extends AbstractController
{
    #[Route('', name: 'app_licencie_index', methods: ['GET'])]
    public function index(
        Request $request,
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        AdhesionRepository $adhesionRepository,
        PresenceRepository $presenceRepository,
    ): Response {
        $saisonId = $request->query->get('saison');
        $saison = $saisonId ? $saisonRepository->find($saisonId) : $saisonRepository->findEnCours();
        if (!$saison) {
            $saison = $saisonRepository->findAllTrieesParDate()[0] ?? null;
        }

        $licencies = $licencieRepository->findAll();

        $tauxPresence = [];
        foreach ($licencies as $licencie) {
            $presences = $presenceRepository->findBy(['licencie' => $licencie]);
            $total = count($presences);
            $venus = count(array_filter($presences, static fn ($p) => $p->isPresent()));
            $tauxPresence[$licencie->getId()] = $total > 0 ? round($venus / $total * 100) : null;
        }

        return $this->render('licencie/index.html.twig', [
            'licencies' => $licencies,
            'saisons' => $saisonRepository->findAllTrieesParDate(),
            'saison' => $saison,
            'adhesions' => $saison ? $adhesionRepository->findParLicenciePourSaison($saison) : [],
            'tauxPresence' => $tauxPresence,
        ]);
    }

    #[Route('/{id}/adhesion', name: 'app_licencie_adhesion', methods: ['GET', 'POST'])]
    public function adhesion(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository): Response
    {
        $saisonId = $request->query->get('saison') ?? $request->request->get('saison');
        $saison = $saisonId ? $saisonRepository->find($saisonId) : $saisonRepository->findEnCours();
        if (!$saison) {
            $saison = $saisonRepository->findAllTrieesParDate()[0] ?? null;
        }
        if (!$saison) {
            $this->addFlash('error', "Aucune saison n'est configurée.");

            return $this->redirectToRoute('app_licencie_index');
        }

        $adhesion = $adhesionRepository->findOneByLicencieEtSaison($licencie, $saison)
            ?? (new Adhesion())->setLicencie($licencie)->setSaison($saison);

        if ($request->isMethod('POST')) {
            $statut = (string) $request->request->get('statut');
            if (!array_key_exists($statut, Adhesion::STATUTS)) {
                $statut = Adhesion::STATUT_EN_ATTENTE;
            }
            $montantTotalRaw = $request->request->get('montantTotal');

            $adhesion
                ->setStatut($statut)
                ->setMontantTotal(null !== $montantTotalRaw && '' !== $montantTotalRaw ? (float) str_replace(',', '.', (string) $montantTotalRaw) : null);

            $entityManager->persist($adhesion);
            $entityManager->flush();

            $this->addFlash('success', 'Adhésion mise à jour.');

            return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saison->getId()]);
        }

        return $this->render('licencie/adhesion.html.twig', [
            'licencie' => $licencie,
            'saison' => $saison,
            'saisons' => $saisonRepository->findAllTrieesParDate(),
            'adhesion' => $adhesion,
        ]);
    }

    #[Route('/{id}/adhesion/paiements', name: 'app_licencie_adhesion_paiement_new', methods: ['POST'])]
    public function ajouterPaiement(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository): Response
    {
        $saison = $saisonRepository->find($request->request->get('saison'));
        if (!$saison) {
            $this->addFlash('error', 'Saison invalide.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $adhesion = $adhesionRepository->findOneByLicencieEtSaison($licencie, $saison)
            ?? (new Adhesion())->setLicencie($licencie)->setSaison($saison);

        $montant = (float) str_replace(',', '.', (string) $request->request->get('montant'));
        $moyen = (string) $request->request->get('moyen');
        if (!array_key_exists($moyen, PaiementAdhesion::MOYENS)) {
            $moyen = PaiementAdhesion::MOYEN_ESPECES;
        }
        $dateRaw = (string) $request->request->get('date');

        if ($montant <= 0) {
            $this->addFlash('error', 'Le montant du versement doit être supérieur à zéro.');

            return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saison->getId()]);
        }

        $paiement = (new PaiementAdhesion())
            ->setAdhesion($adhesion)
            ->setMontant($montant)
            ->setDate($dateRaw ? new \DateTimeImmutable($dateRaw) : new \DateTimeImmutable())
            ->setMoyen($moyen)
            ->setNumeroCheque(PaiementAdhesion::MOYEN_CHEQUE === $moyen ? ((string) $request->request->get('numeroCheque') ?: null) : null);

        $entityManager->persist($adhesion);
        $entityManager->persist($paiement);

        // Passage automatique à "Payée" une fois le montant total couvert (si renseigné).
        if (null !== $adhesion->getMontantTotal() && $adhesion->getMontantRestant() - $montant <= 0) {
            $adhesion->setStatut(Adhesion::STATUT_PAYEE);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Versement enregistré.');

        return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saison->getId()]);
    }

    #[Route('/{id}/adhesion/paiements/{paiementId}/supprimer', name: 'app_licencie_adhesion_paiement_delete', methods: ['POST'])]
    public function supprimerPaiement(Request $request, Licencie $licencie, int $paiementId, EntityManagerInterface $entityManager, PaiementAdhesionRepository $paiementRepository): Response
    {
        $paiement = $paiementRepository->find($paiementId);
        if ($paiement && $paiement->getAdhesion()->getLicencie()->getId() === $licencie->getId()
            && $this->isCsrfTokenValid('delete-paiement-'.$paiementId, (string) $request->request->get('_token'))) {
            $saisonId = $paiement->getAdhesion()->getSaison()->getId();
            $entityManager->remove($paiement);
            $entityManager->flush();
            $this->addFlash('success', 'Versement supprimé.');

            return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saisonId]);
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/import/modele', name: 'app_licencie_import_modele', methods: ['GET'])]
    public function importModele(): Response
    {
        $lignes = [
            ['prenom', 'nom', 'email', 'bureau', 'entraineur'],
            ['Jean', 'Dupont', 'jean.dupont@example.com', 'non', 'non'],
            ['Marie', 'Martin', 'marie.martin@example.com', 'non', 'oui'],
        ];

        $handle = fopen('php://temp', 'r+');
        foreach ($lignes as $ligne) {
            fputcsv($handle, $ligne, ';');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $response = new Response("\xEF\xBB\xBF".$csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="modele-import-licencies.csv"');

        return $response;
    }

    #[Route('/import', name: 'app_licencie_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        InvitationMailer $invitationMailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $fichier = $request->files->get('fichier');
            if (!$fichier) {
                $this->addFlash('error', 'Aucun fichier sélectionné.');

                return $this->redirectToRoute('app_licencie_import');
            }

            $handle = fopen($fichier->getPathname(), 'r');
            if (!$handle) {
                $this->addFlash('error', 'Impossible de lire le fichier.');

                return $this->redirectToRoute('app_licencie_import');
            }

            $premiereLigne = fgetcsv($handle, 0, ';');
            if ($premiereLigne && strtolower(trim((string) $premiereLigne[0])) !== 'prenom') {
                // Le fichier ne commence pas par l'en-tête attendu : on la traite comme une donnée.
                rewind($handle);
            }

            $creees = 0;
            $ignorees = [];
            $echecsEnvoi = [];
            $numeroLigne = 1;

            while (($ligne = fgetcsv($handle, 0, ';')) !== false) {
                ++$numeroLigne;
                if (count(array_filter($ligne, static fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $prenom = trim((string) ($ligne[0] ?? ''));
                $nom = trim((string) ($ligne[1] ?? ''));
                $email = trim((string) ($ligne[2] ?? ''));
                $estBureau = in_array(strtolower(trim((string) ($ligne[3] ?? ''))), ['oui', '1', 'true', 'yes'], true);
                $estEntraineur = in_array(strtolower(trim((string) ($ligne[4] ?? ''))), ['oui', '1', 'true', 'yes'], true);

                if (!$prenom || !$nom || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ignorees[] = sprintf('Ligne %d : données invalides (%s)', $numeroLigne, $email ?: 'email manquant');

                    continue;
                }

                if ($entityManager->getRepository(Licencie::class)->findOneBy(['email' => $email])) {
                    $ignorees[] = sprintf('Ligne %d : %s existe déjà', $numeroLigne, $email);

                    continue;
                }

                $roles = [];
                if ($estBureau) {
                    $roles[] = Licencie::ROLE_BUREAU;
                }
                if ($estEntraineur) {
                    $roles[] = Licencie::ROLE_ENTRAINEUR;
                }

                $licencie = (new Licencie())
                    ->setEmail($email)
                    ->setPrenom($prenom)
                    ->setNom($nom)
                    ->setRoles($roles)
                    ->setMustChangePassword(true);
                $licencie->setPassword($passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));
                $token = $licencie->generateActivationToken();

                $entityManager->persist($licencie);
                $entityManager->flush();

                if (!$invitationMailer->envoyerInvitation($licencie, $token)) {
                    $echecsEnvoi[] = $email;
                }
                ++$creees;
            }

            fclose($handle);

            if ($creees > 0) {
                $this->addFlash('success', sprintf('%d licencié(s) créé(s).', $creees));
            }
            if ($echecsEnvoi) {
                $this->addFlash('error', sprintf(
                    "L'email d'invitation n'a pas pu être envoyé à : %s. Les comptes ont bien été créés ; utilisez « Renvoyer l'invitation » une fois le problème résolu.",
                    implode(', ', $echecsEnvoi)
                ));
            }
            if ($ignorees) {
                $this->addFlash('error', sprintf('%d ligne(s) ignorée(s) : %s', count($ignorees), implode(' — ', $ignorees)));
            }
            if (0 === $creees && !$ignorees) {
                $this->addFlash('error', 'Le fichier ne contient aucune ligne exploitable.');
            }

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/import.html.twig');
    }

    #[Route('/nouveau', name: 'app_licencie_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        InvitationMailer $invitationMailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email')) ?: null;
            $prenom = (string) $request->request->get('prenom');
            $nom = (string) $request->request->get('nom');
            $roles = $request->request->all('roles');
            $dateNaissance = (string) $request->request->get('dateNaissance');

            if (null !== $email && $entityManager->getRepository(Licencie::class)->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');

                return $this->redirectToRoute('app_licencie_new');
            }

            $licencie = (new Licencie())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR, Licencie::ROLE_CORDEUR, Licencie::ROLE_STOCK])))
                ->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null)
                ->setMustChangePassword(true);

            $this->appliquerChampsMineur($licencie, $request, $entityManager);

            // Mot de passe temporaire inutilisable : le licencié le définit lui-même via le lien d'activation
            // (ou, pour un mineur sans compte propre, n'est jamais utilisé).
            $licencie->setPassword($passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));

            $token = $licencie->generateActivationToken();

            $entityManager->persist($licencie);
            $entityManager->flush();

            if (null === $email) {
                $this->addFlash('success', 'Licencié créé (sans compte de connexion, rattaché à son/ses responsable(s) légal(aux)).');
            } elseif ($invitationMailer->envoyerInvitation($licencie, $token)) {
                $this->addFlash('success', sprintf('Licencié créé, un email d\'activation a été envoyé à %s.', $email));
            } else {
                $this->addFlash('error', sprintf(
                    "Licencié créé, mais l'email d'invitation n'a pas pu être envoyé à %s. Utilisez « Renvoyer l'invitation » une fois le problème résolu.",
                    $email
                ));
            }

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => null,
            'responsablesPossibles' => $entityManager->getRepository(Licencie::class)->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_licencie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Licencie $licencie, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $roles = $request->request->all('roles');
            $dateNaissance = (string) $request->request->get('dateNaissance');
            $numeroLicence = (string) $request->request->get('numeroLicence');
            $classementSimple = $this->normaliserClassement($request->request->get('classementSimple'));
            $classementDouble = $this->normaliserClassement($request->request->get('classementDouble'));
            $classementMixte = $this->normaliserClassement($request->request->get('classementMixte'));

            $anciensClassements = [$licencie->getClassementSimple(), $licencie->getClassementDouble(), $licencie->getClassementMixte()];

            $email = trim((string) $request->request->get('email')) ?: null;
            if (null !== $email && $email !== $licencie->getEmail()) {
                $existant = $entityManager->getRepository(Licencie::class)->findOneBy(['email' => $email]);
                if ($existant && $existant->getId() !== $licencie->getId()) {
                    $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');

                    return $this->redirectToRoute('app_licencie_edit', ['id' => $licencie->getId()]);
                }
            }

            $licencie
                ->setEmail($email)
                ->setPrenom((string) $request->request->get('prenom'))
                ->setNom((string) $request->request->get('nom'))
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR, Licencie::ROLE_CORDEUR, Licencie::ROLE_STOCK])))
                ->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null)
                ->setNumeroLicence($numeroLicence ?: null)
                ->setGenre((string) $request->request->get('genre') ?: null)
                ->setClassementSimple($classementSimple)
                ->setClassementDouble($classementDouble)
                ->setClassementMixte($classementMixte);

            $this->appliquerChampsMineur($licencie, $request, $entityManager);

            $nouveauxClassements = [$licencie->getClassementSimple(), $licencie->getClassementDouble(), $licencie->getClassementMixte()];
            if ($anciensClassements !== $nouveauxClassements) {
                $licencie->setClassementMisAJourLe(new \DateTimeImmutable());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Licencié modifié.');

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => $licencie,
            'responsablesPossibles' => $entityManager->getRepository(Licencie::class)->findBy([], ['nom' => 'ASC']),
        ]);
    }

    private function appliquerChampsMineur(Licencie $licencie, Request $request, EntityManagerInterface $entityManager): void
    {
        $responsable1Id = $request->request->get('responsableLegal1');
        $responsable2Id = $request->request->get('responsableLegal2');
        $repository = $entityManager->getRepository(Licencie::class);

        $responsable1 = $responsable1Id ? $repository->find($responsable1Id) : null;
        $responsable2 = $responsable2Id ? $repository->find($responsable2Id) : null;

        $licencie
            ->setResponsableLegal1($responsable1 !== $licencie ? $responsable1 : null)
            ->setResponsableLegal2($responsable2 !== $licencie ? $responsable2 : null)
            ->setPersonnesAutoriseesRecuperation((string) $request->request->get('personnesAutoriseesRecuperation') ?: null)
            ->setContactUrgenceNom((string) $request->request->get('contactUrgenceNom') ?: null)
            ->setContactUrgenceTelephone((string) $request->request->get('contactUrgenceTelephone') ?: null)
            ->setAutorisationSortieSeul((bool) $request->request->get('autorisationSortieSeul'))
            ->setDroitImage((bool) $request->request->get('droitImage'))
            ->setInformationsSante((string) $request->request->get('informationsSante') ?: null);
    }

    #[Route('/{id}/renvoyer-invitation', name: 'app_licencie_renvoyer_invitation', methods: ['POST'])]
    public function renvoyerInvitation(Licencie $licencie, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if (!$licencie->aUnCompte()) {
            $this->addFlash('error', "Ce licencié n'a pas de compte de connexion (rattaché à un responsable légal).");

            return $this->redirectToRoute('app_licencie_index');
        }

        if (!$licencie->mustChangePassword()) {
            $this->addFlash('error', 'Ce compte est déjà activé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $token = $licencie->generateActivationToken();
        $entityManager->flush();

        if ($invitationMailer->envoyerInvitation($licencie, $token)) {
            $this->addFlash('success', sprintf('Invitation renvoyée à %s.', $licencie->getEmail()));
        } else {
            $this->addFlash('error', sprintf("L'email n'a pas pu être envoyé à %s. Réessayez plus tard.", $licencie->getEmail()));
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/activer', name: 'app_licencie_toggle_actif', methods: ['POST'])]
    public function toggleActif(Licencie $licencie, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        if ($moi->getId() === $licencie->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
            $this->addFlash('error', 'Le compte administrateur par défaut ne peut pas être désactivé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $licencie->setActif(!$licencie->isActif());
        $entityManager->flush();

        $this->addFlash('success', $licencie->isActif() ? 'Compte réactivé.' : 'Compte désactivé.');

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/supprimer', name: 'app_licencie_delete', methods: ['POST'])]
    public function delete(Request $request, Licencie $licencie, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        if ($moi->getId() === $licencie->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
            $this->addFlash('error', 'Le compte administrateur par défaut ne peut pas être supprimé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if (!$this->isCsrfTokenValid('delete-licencie-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_licencie_index');
        }

        try {
            $entityManager->remove($licencie);
            $entityManager->flush();
            $this->addFlash('success', 'Licencié supprimé.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Impossible de supprimer ce licencié : des données lui sont liées (présences, créneaux encadrés, mouvements de stock...). Désactivez plutôt son compte, ou utilisez « Forcer la suppression ».');
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    /**
     * Supprime le licencié et absolument toutes les données qui lui sont directement liées
     * (présences, inscriptions, convocations, clés de gymnase, mouvements de stock, demandes de
     * cordage, raquettes, adhésion et paiements). Les données où il n'est qu'une référence
     * secondaire (créneau encadré, équipe capitainée, demande de cordage traitée en tant que
     * cordeur) sont détachées plutôt que supprimées.
     */
    #[Route('/{id}/supprimer-de-force', name: 'app_licencie_force_delete', methods: ['POST'])]
    public function forceDelete(Request $request, Licencie $licencie, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        if ($moi->getId() === $licencie->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
            $this->addFlash('error', 'Le compte administrateur par défaut ne peut pas être supprimé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if (!$this->isCsrfTokenValid('force-delete-licencie-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_licencie_index');
        }

        // Références secondaires : on détache plutôt que supprimer les données qui n'appartiennent pas au licencié.
        foreach ($entityManager->getRepository(\App\Entity\Creneau::class)->findBy(['entraineur' => $licencie]) as $creneau) {
            $creneau->setEntraineur(null);
        }
        foreach ($entityManager->getRepository(\App\Entity\Equipe::class)->findBy(['capitaine' => $licencie]) as $equipe) {
            $equipe->setCapitaine(null);
        }
        foreach ($entityManager->getRepository(\App\Entity\Equipe::class)->findAll() as $equipe) {
            $equipe->getMembres()->removeElement($licencie);
        }
        foreach ($entityManager->getRepository(DemandeCordage::class)->findBy(['cordeur' => $licencie]) as $demande) {
            $demande->setCordeur(null);
        }

        // Données appartenant au licencié : suppression complète.
        foreach ($entityManager->getRepository(Presence::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(Convocation::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(Inscription::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(CleGymnase::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(StockMouvementVetement::class)->findBy(['auteur' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(StockMouvementVolant::class)->findBy(['auteur' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(DemandeCordage::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(Raquette::class)->findBy(['licencie' => $licencie]) as $entite) {
            $entityManager->remove($entite);
        }
        foreach ($entityManager->getRepository(Adhesion::class)->findBy(['licencie' => $licencie]) as $adhesion) {
            foreach ($adhesion->getPaiements() as $paiement) {
                $entityManager->remove($paiement);
            }
            $entityManager->remove($adhesion);
        }

        $entityManager->remove($licencie);
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s a été supprimé, avec toutes les données qui lui étaient liées.', $licencie->getNomComplet()));

        return $this->redirectToRoute('app_licencie_index');
    }

    private function normaliserClassement(mixed $valeur): ?string
    {
        $valeur = (string) $valeur;

        return in_array($valeur, ClassementFfbad::CODES, true) ? $valeur : null;
    }
}
