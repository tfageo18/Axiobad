<?php

namespace App\Controller;

use App\Badminton\CategorieAge;
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
use App\Ffbad\LicencieSynchroniseur;
use App\Ffbad\MyFfbadClient;
use App\Repository\AdhesionRepository;
use App\Repository\EquipeRepository;
use App\Repository\LicencieRepository;
use App\Repository\PaiementAdhesionRepository;
use App\Repository\ParametresClubRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Service\AnonymisationLicencieService;
use App\Service\AuditLogger;
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
        ParametresClubRepository $parametresClubRepository,
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
            'parametresClub' => $parametresClubRepository->obtenir(),
        ]);
    }

    #[Route('/synchroniser-myffbad', name: 'app_licencie_synchroniser_myffbad_tous', methods: ['POST'])]
    public function synchroniserMyFfbadTous(Request $request, LicencieSynchroniseur $licencieSynchroniseur): Response
    {
        if (!$this->isCsrfTokenValid('synchroniser-myffbad-tous', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $resultat = $licencieSynchroniseur->synchroniserTous();

        if (LicencieSynchroniseur::ERREUR_URL_NON_CONFIGUREE === $resultat['erreur']) {
            $this->addFlash('error', "L'URL de l'effectif MyFFBaD n'est pas configurée (Paramètres du club).");
        } elseif (LicencieSynchroniseur::ERREUR_AUCUNE_DONNEE === $resultat['erreur']) {
            $this->addFlash('error', "Aucune donnée récupérée depuis MyFFBaD — l'URL est peut-être incorrecte, ou le site est momentanément inaccessible.");
        } else {
            $this->addFlash('success', sprintf('Synchronisation MyFFBaD : %d licencié(s) mis à jour, %d sans correspondance.', $resultat['misAJour'], $resultat['nonTrouves']));
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/synchroniser-myffbad', name: 'app_licencie_synchroniser_myffbad', methods: ['POST'])]
    public function synchroniserMyFfbad(Request $request, Licencie $licencie, LicencieSynchroniseur $licencieSynchroniseur): Response
    {
        if (!$this->isCsrfTokenValid('synchroniser-myffbad-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $resultat = $licencieSynchroniseur->synchroniserUn($licencie);

        if (LicencieSynchroniseur::ERREUR_URL_NON_CONFIGUREE === $resultat['erreur']) {
            $this->addFlash('error', "L'URL de l'effectif MyFFBaD n'est pas configurée (Paramètres du club).");
        } elseif (!$resultat['trouve']) {
            $this->addFlash('error', sprintf('Aucune correspondance trouvée sur MyFFBaD pour %s.', $licencie->getNomComplet()));
        } else {
            $correspondance = $resultat['correspondance'];
            $this->addFlash('success', sprintf(
                '%s synchronisé — n° licence %s, classements S/D/M : %s / %s / %s.',
                $licencie->getNomComplet(),
                $correspondance['numeroLicence'],
                $correspondance['classementSimple'] ?? '-',
                $correspondance['classementDouble'] ?? '-',
                $correspondance['classementMixte'] ?? '-',
            ));
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/modifier')) {
            return $this->redirectToRoute('app_licencie_edit', ['id' => $licencie->getId()]);
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/adhesion', name: 'app_licencie_adhesion', methods: ['GET', 'POST'])]
    public function adhesion(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository, AuditLogger $auditLogger): Response
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
            if (!$this->isCsrfTokenValid('licencie-adhesion-'.$licencie->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saison->getId()]);
            }

            $ancienStatut = $adhesion->getStatut();
            $ancienMontant = $adhesion->getMontantTotal();

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

            $auditLogger->log(
                AuditLogger::ADHESION_MODIFIEE,
                'Adhesion',
                sprintf('%s — %s', $licencie->getNomComplet(), $saison->getLibelle()),
                sprintf('%s / %s €', $ancienStatut, $ancienMontant ?? '-'),
                sprintf('%s / %s €', $statut, $adhesion->getMontantTotal() ?? '-')
            );

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
    public function ajouterPaiement(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('licencie-ajouter-paiement-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

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

        $auditLogger->log(
            AuditLogger::PAIEMENT_MODIFIE,
            'PaiementAdhesion',
            sprintf('%s — %s', $licencie->getNomComplet(), $saison->getLibelle()),
            null,
            sprintf('+%.2f € (%s)', $montant, PaiementAdhesion::MOYENS[$moyen] ?? $moyen)
        );

        $this->addFlash('success', 'Versement enregistré.');

        return $this->redirectToRoute('app_licencie_adhesion', ['id' => $licencie->getId(), 'saison' => $saison->getId()]);
    }

    #[Route('/{id}/adhesion/paiements/{paiementId}/supprimer', name: 'app_licencie_adhesion_paiement_delete', methods: ['POST'])]
    public function supprimerPaiement(Request $request, Licencie $licencie, int $paiementId, EntityManagerInterface $entityManager, PaiementAdhesionRepository $paiementRepository, AuditLogger $auditLogger): Response
    {
        $paiement = $paiementRepository->find($paiementId);
        if ($paiement && $paiement->getAdhesion()->getLicencie()->getId() === $licencie->getId()
            && $this->isCsrfTokenValid('delete-paiement-'.$paiementId, (string) $request->request->get('_token'))) {
            $saisonId = $paiement->getAdhesion()->getSaison()->getId();
            $ancienneValeur = sprintf('%.2f € (%s)', $paiement->getMontant(), PaiementAdhesion::MOYENS[$paiement->getMoyen()] ?? $paiement->getMoyen());
            $entityManager->remove($paiement);
            $entityManager->flush();

            $auditLogger->log(
                AuditLogger::PAIEMENT_MODIFIE,
                'PaiementAdhesion',
                $licencie->getNomComplet(),
                $ancienneValeur,
                'Supprimé'
            );

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

    #[Route('/importer-myffbad', name: 'app_licencie_import_myffbad', methods: ['GET'])]
    public function importMyFfbad(ParametresClubRepository $parametresClubRepository, MyFfbadClient $myFfbadClient, LicencieRepository $licencieRepository): Response
    {
        $urlEffectif = $parametresClubRepository->obtenir()->getUrlEffectifMyFfbad();
        if (!$urlEffectif) {
            $this->addFlash('error', "L'URL de l'effectif MyFFBaD n'est pas configurée (Paramètres du club).");

            return $this->redirectToRoute('app_licencie_index');
        }

        $effectif = $myFfbadClient->recupererEffectifComplet($urlEffectif);
        if (!$effectif) {
            $this->addFlash('error', "Aucune donnée récupérée depuis MyFFBaD — l'URL est peut-être incorrecte, ou le site est momentanément inaccessible.");

            return $this->redirectToRoute('app_licencie_index');
        }

        $numerosDejaPresents = array_filter(array_map(
            static fn (Licencie $l) => $l->getNumeroLicence(),
            $licencieRepository->findAll()
        ));

        $aImporter = array_values(array_filter(
            $effectif,
            static fn (array $joueur) => !in_array($joueur['numeroLicence'], $numerosDejaPresents, true)
        ));

        return $this->render('licencie/import_myffbad.html.twig', [
            'joueurs' => $aImporter,
            'nombreDejaPresents' => count($effectif) - count($aImporter),
        ]);
    }

    #[Route('/importer-myffbad', name: 'app_licencie_import_myffbad_valider', methods: ['POST'])]
    public function importMyFfbadValider(
        Request $request,
        ParametresClubRepository $parametresClubRepository,
        MyFfbadClient $myFfbadClient,
        LicencieRepository $licencieRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('importer-myffbad', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_import_myffbad');
        }

        $selection = array_map('strval', $request->request->all('numerosLicence'));
        if (!$selection) {
            $this->addFlash('error', 'Aucun licencié sélectionné.');

            return $this->redirectToRoute('app_licencie_import_myffbad');
        }

        $urlEffectif = $parametresClubRepository->obtenir()->getUrlEffectifMyFfbad();
        if (!$urlEffectif) {
            $this->addFlash('error', "L'URL de l'effectif MyFFBaD n'est pas configurée (Paramètres du club).");

            return $this->redirectToRoute('app_licencie_index');
        }

        $effectif = $myFfbadClient->recupererEffectifComplet($urlEffectif);

        $numerosDejaPresents = array_filter(array_map(
            static fn (Licencie $l) => $l->getNumeroLicence(),
            $licencieRepository->findAll()
        ));

        $crees = 0;
        foreach ($effectif as $joueur) {
            if (!in_array($joueur['numeroLicence'], $selection, true)) {
                continue;
            }
            if (in_array($joueur['numeroLicence'], $numerosDejaPresents, true)) {
                continue; // déjà importé entre-temps (double clic, autre onglet...)
            }

            $licencie = (new Licencie())
                ->setPrenom($joueur['prenom'])
                ->setNom($joueur['nom'])
                ->setGenre($joueur['genre'])
                ->setNumeroLicence($joueur['numeroLicence'])
                ->setClassementSimple($joueur['classementSimple'])
                ->setClassementDouble($joueur['classementDouble'])
                ->setClassementMixte($joueur['classementMixte'])
                ->setClassementMisAJourLe(new \DateTimeImmutable())
                ->setMyFfbadCategorieAge($joueur['categorieAge'] ?? null)
                ->setCategorieAge(CategorieAge::depuisLibelleFfbad($joueur['categorieAge'] ?? null))
                ->setMyFfbadEstMineur($joueur['estMineur'] ?? null)
                ->setMustChangePassword(true);
            // Pas d'email pour l'instant (MyFFBaD n'en fournit pas) : compte créé sans accès de
            // connexion tant que le bureau n'a pas renseigné une adresse — voir « Envoyer
            // l'invitation » sur la liste des licenciés.
            $licencie->setPassword($passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));

            $entityManager->persist($licencie);
            ++$crees;
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            '%d licencié(s) importé(s) depuis MyFFBaD. Il ne reste plus qu\'à renseigner leur adresse email (bouton « Envoyer l\'invitation » sur la liste) pour leur donner accès à leur compte.',
            $crees
        ));

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/envoyer-invitation', name: 'app_licencie_envoyer_invitation', methods: ['POST'])]
    public function envoyerInvitation(Licencie $licencie, Request $request, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if (!$this->isCsrfTokenValid('envoyer-invitation-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->aUnCompte()) {
            $this->addFlash('error', 'Ce licencié a déjà un compte — utilisez « Renvoyer l\'invitation » ou « Réinitialiser le mot de passe ».');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->estMineur()) {
            $this->addFlash('error', 'Ce licencié est mineur — pas de compte de connexion direct, utilisez le compte de son responsable légal.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $email = trim((string) $request->request->get('email'));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($entityManager->getRepository(Licencie::class)->findOneBy(['email' => $email])) {
            $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $licencie->setEmail($email);
        $token = $licencie->generateActivationToken();
        $entityManager->flush();

        if ($invitationMailer->envoyerInvitation($licencie, $token)) {
            $this->addFlash('success', sprintf('Compte créé et invitation envoyée à %s.', $email));
        } else {
            $this->addFlash('error', sprintf("L'email a été enregistré, mais l'invitation n'a pas pu être envoyée à %s. Utilisez « Renvoyer l'invitation » une fois le problème résolu.", $email));
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/nouveau', name: 'app_licencie_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        InvitationMailer $invitationMailer,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('licencie-new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_licencie_new');
            }

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
                ->setNotificationsActivees((bool) $request->request->get('notificationsActivees'))
                ->setMustChangePassword(true);

            $erreurMineur = $this->appliquerChampsMineur($licencie, $request, $entityManager);
            if (null !== $erreurMineur) {
                $this->addFlash('error', $erreurMineur);

                return $this->redirectToRoute('app_licencie_new');
            }

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
    public function edit(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, AuditLogger $auditLogger, EquipeRepository $equipeRepository, ParametresClubRepository $parametresClubRepository): Response
    {
        $sesEquipes = $equipeRepository->findByMembre($licencie);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('licencie-edit-'.$licencie->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_licencie_edit', ['id' => $licencie->getId()]);
            }

            $anciensRoles = $licencie->getRoles();
            $ancienResponsable1 = $licencie->getResponsableLegal1();
            $ancienResponsable2 = $licencie->getResponsableLegal2();
            $ancienneSante = $licencie->getInformationsSante();
            $ancienConsentement = $licencie->isConsentementDonneesSante();

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
                ->setCategorieAge((string) $request->request->get('categorieAge') ?: null)
                ->setClassementSimple($classementSimple)
                ->setClassementDouble($classementDouble)
                ->setClassementMixte($classementMixte)
                ->setNotificationsActivees((bool) $request->request->get('notificationsActivees'));

            $equipePrefereeId = $request->request->get('equipePreferee');
            $equipePreferee = $equipePrefereeId ? $equipeRepository->find($equipePrefereeId) : null;
            $licencie->setEquipePreferee($equipePreferee && in_array($equipePreferee, $sesEquipes, true) ? $equipePreferee : null);

            $erreurMineur = $this->appliquerChampsMineur($licencie, $request, $entityManager);
            if (null !== $erreurMineur) {
                $this->addFlash('error', $erreurMineur);

                return $this->redirectToRoute('app_licencie_edit', ['id' => $licencie->getId()]);
            }

            $nouveauxClassements = [$licencie->getClassementSimple(), $licencie->getClassementDouble(), $licencie->getClassementMixte()];
            if ($anciensClassements !== $nouveauxClassements) {
                $licencie->setClassementMisAJourLe(new \DateTimeImmutable());
            }

            $entityManager->flush();

            if ($anciensRoles !== $licencie->getRoles()) {
                $auditLogger->log(AuditLogger::ROLE_CHANGE, 'Licencie', $licencie->getNomComplet(), implode(', ', $anciensRoles), implode(', ', $licencie->getRoles()));
            }
            if ($ancienResponsable1 !== $licencie->getResponsableLegal1() || $ancienResponsable2 !== $licencie->getResponsableLegal2()) {
                $auditLogger->log(
                    AuditLogger::RESPONSABLE_LEGAL_CHANGE,
                    'Licencie',
                    $licencie->getNomComplet(),
                    implode(', ', array_filter([$ancienResponsable1?->getNomComplet(), $ancienResponsable2?->getNomComplet()])) ?: 'Aucun',
                    implode(', ', array_filter([$licencie->getResponsableLegal1()?->getNomComplet(), $licencie->getResponsableLegal2()?->getNomComplet()])) ?: 'Aucun'
                );
            }
            if ($ancienneSante !== $licencie->getInformationsSante()) {
                $auditLogger->log(AuditLogger::SANTE_MODIFIEE, 'Licencie', $licencie->getNomComplet());
            }
            if ($ancienConsentement !== $licencie->isConsentementDonneesSante()) {
                $auditLogger->log(
                    AuditLogger::CONSENTEMENT_SANTE_CHANGE,
                    'Licencie',
                    $licencie->getNomComplet(),
                    $ancienConsentement ? 'Accordé' : 'Refusé/absent',
                    $licencie->isConsentementDonneesSante() ? 'Accordé' : 'Retiré'
                );
            }

            $this->addFlash('success', 'Licencié modifié.');

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => $licencie,
            'responsablesPossibles' => $entityManager->getRepository(Licencie::class)->findBy([], ['nom' => 'ASC']),
            'sesEquipes' => $sesEquipes,
            'parametresClub' => $parametresClubRepository->obtenir(),
        ]);
    }

    /**
     * @return string|null message d'erreur si la sauvegarde doit être bloquée (ex : donnée de santé
     *                      sans consentement), null si tout est valide et a été appliqué
     */
    private function appliquerChampsMineur(Licencie $licencie, Request $request, EntityManagerInterface $entityManager): ?string
    {
        $responsable1Id = $request->request->get('responsableLegal1');
        $responsable2Id = $request->request->get('responsableLegal2');
        $repository = $entityManager->getRepository(Licencie::class);

        $responsable1 = $responsable1Id ? $repository->find($responsable1Id) : null;
        $responsable2 = $responsable2Id ? $repository->find($responsable2Id) : null;

        $informationsSante = (string) $request->request->get('informationsSante') ?: null;
        $consentementSante = (bool) $request->request->get('consentementDonneesSante');

        if (null !== $informationsSante && !$consentementSante) {
            return "Le consentement explicite est obligatoire pour enregistrer une information de santé (donnée sensible, RGPD art. 9).";
        }

        if (null === $informationsSante) {
            // Pas de donnée de santé : pas de consentement à conserver.
            $consentementSante = false;
        }

        $licencie
            ->setResponsableLegal1($responsable1 !== $licencie ? $responsable1 : null)
            ->setResponsableLegal2($responsable2 !== $licencie ? $responsable2 : null)
            ->setPersonnesAutoriseesRecuperation((string) $request->request->get('personnesAutoriseesRecuperation') ?: null)
            ->setContactUrgenceNom((string) $request->request->get('contactUrgenceNom') ?: null)
            ->setContactUrgenceTelephone((string) $request->request->get('contactUrgenceTelephone') ?: null)
            ->setAutorisationSortieSeul((bool) $request->request->get('autorisationSortieSeul'))
            ->setDroitImage((bool) $request->request->get('droitImage'))
            ->setInformationsSante($informationsSante)
            ->setConsentementDonneesSante($consentementSante);

        return null;
    }

    #[Route('/{id}/renvoyer-invitation', name: 'app_licencie_renvoyer_invitation', methods: ['POST'])]
    public function renvoyerInvitation(Licencie $licencie, Request $request, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if (!$this->isCsrfTokenValid('renvoyer-invitation-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

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

    #[Route('/{id}/reinitialiser-mot-de-passe', name: 'app_licencie_reinitialiser_mot_de_passe', methods: ['POST'])]
    public function reinitialiserMotDePasse(Licencie $licencie, Request $request, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if (!$this->isCsrfTokenValid('reinitialiser-mot-de-passe-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if (!$licencie->aUnCompte()) {
            $this->addFlash('error', "Ce licencié n'a pas de compte de connexion (rattaché à un responsable légal).");

            return $this->redirectToRoute('app_licencie_index');
        }

        $token = $licencie->generateActivationToken();
        $entityManager->flush();

        if ($invitationMailer->envoyerReinitialisationMotDePasse($licencie, $token)) {
            $this->addFlash('success', sprintf('Lien de réinitialisation envoyé à %s.', $licencie->getEmail()));
        } else {
            $this->addFlash('error', sprintf("L'email n'a pas pu être envoyé à %s. Réessayez plus tard.", $licencie->getEmail()));
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/activer', name: 'app_licencie_toggle_actif', methods: ['POST'])]
    public function toggleActif(Licencie $licencie, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('licencie-toggle-actif-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_licencie_index');
        }

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
        $licencie->setDesactiveLe($licencie->isActif() ? null : new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', $licencie->isActif() ? 'Compte réactivé.' : 'Compte désactivé.');

        return $this->redirectToRoute('app_licencie_index');
    }

    /**
     * Anonymise un compte (RGPD art. 17) : à la différence de la suppression, la ligne et les
     * données comptables liées (adhésions, paiements) sont conservées, mais l'identité et les
     * données personnelles sont effacées de façon irréversible. Réservé aux comptes désactivés.
     */
    #[Route('/{id}/anonymiser', name: 'app_licencie_anonymiser', methods: ['POST'])]
    public function anonymiser(Request $request, Licencie $licencie, AnonymisationLicencieService $anonymisationService, AuditLogger $auditLogger): Response
    {
        /** @var Licencie $moi */
        $moi = $this->getUser();
        if ($moi->getId() === $licencie->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas anonymiser votre propre compte.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
            $this->addFlash('error', 'Le compte administrateur par défaut ne peut pas être anonymisé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if ($licencie->isActif()) {
            $this->addFlash('error', 'Désactivez le compte avant de pouvoir l\'anonymiser.');

            return $this->redirectToRoute('app_licencie_index');
        }

        if (!$this->isCsrfTokenValid('anonymiser-licencie-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_licencie_index');
        }

        $nomLicencie = $licencie->getNomComplet();
        $anonymisationService->anonymiser($licencie);
        $auditLogger->log(AuditLogger::COMPTE_ANONYMISE, 'Licencie', $nomLicencie);

        $this->addFlash('success', 'Compte anonymisé : son identité et ses données personnelles ont été effacées, ses données comptables (adhésions, paiements) sont conservées.');

        return $this->redirectToRoute('app_licencie_index');
    }

    #[Route('/{id}/supprimer', name: 'app_licencie_delete', methods: ['POST'])]
    public function delete(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
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

        $nomLicencie = $licencie->getNomComplet();

        try {
            $entityManager->remove($licencie);
            $entityManager->flush();
            $auditLogger->log(AuditLogger::COMPTE_SUPPRIME, 'Licencie', $nomLicencie);
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
    public function forceDelete(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
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

        $nomLicencie = $licencie->getNomComplet();
        $entityManager->remove($licencie);
        $entityManager->flush();

        $auditLogger->log(AuditLogger::COMPTE_SUPPRIME, 'Licencie', $nomLicencie, null, 'Suppression forcée (avec données liées)');

        $this->addFlash('success', sprintf('%s a été supprimé, avec toutes les données qui lui étaient liées.', $nomLicencie));

        return $this->redirectToRoute('app_licencie_index');
    }

    private function normaliserClassement(mixed $valeur): ?string
    {
        $valeur = (string) $valeur;

        return in_array($valeur, ClassementFfbad::CODES, true) ? $valeur : null;
    }
}
