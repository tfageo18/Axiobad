<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Entity\PaiementAdhesion;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\PaiementAdhesionRepository;
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
    ): Response {
        $saisonId = $request->query->get('saison');
        $saison = $saisonId ? $saisonRepository->find($saisonId) : $saisonRepository->findEnCours();
        if (!$saison) {
            $saison = $saisonRepository->findAllTrieesParDate()[0] ?? null;
        }

        return $this->render('licencie/index.html.twig', [
            'licencies' => $licencieRepository->findAll(),
            'saisons' => $saisonRepository->findAllTrieesParDate(),
            'saison' => $saison,
            'adhesions' => $saison ? $adhesionRepository->findParLicenciePourSaison($saison) : [],
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

                $invitationMailer->envoyerInvitation($licencie, $token);
                ++$creees;
            }

            fclose($handle);

            if ($creees > 0) {
                $this->addFlash('success', sprintf('%d licencié(s) créé(s), invitation envoyée à chacun.', $creees));
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
            $email = (string) $request->request->get('email');
            $prenom = (string) $request->request->get('prenom');
            $nom = (string) $request->request->get('nom');
            $roles = $request->request->all('roles');

            $licencie = (new Licencie())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR, Licencie::ROLE_CORDEUR])))
                ->setMustChangePassword(true);

            // Mot de passe temporaire inutilisable : le licencié le définit lui-même via le lien d'activation.
            $licencie->setPassword($passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));

            $token = $licencie->generateActivationToken();

            $entityManager->persist($licencie);
            $entityManager->flush();

            $invitationMailer->envoyerInvitation($licencie, $token);

            $this->addFlash('success', sprintf('Licencié créé, un email d\'activation a été envoyé à %s.', $email));

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => null,
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

            $licencie
                ->setEmail((string) $request->request->get('email'))
                ->setPrenom((string) $request->request->get('prenom'))
                ->setNom((string) $request->request->get('nom'))
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR, Licencie::ROLE_CORDEUR])))
                ->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null)
                ->setNumeroLicence($numeroLicence ?: null)
                ->setGenre((string) $request->request->get('genre') ?: null)
                ->setClassementSimple($classementSimple)
                ->setClassementDouble($classementDouble)
                ->setClassementMixte($classementMixte);

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
        ]);
    }

    #[Route('/{id}/renvoyer-invitation', name: 'app_licencie_renvoyer_invitation', methods: ['POST'])]
    public function renvoyerInvitation(Licencie $licencie, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if (!$licencie->mustChangePassword()) {
            $this->addFlash('error', 'Ce compte est déjà activé.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $token = $licencie->generateActivationToken();
        $entityManager->flush();

        $invitationMailer->envoyerInvitation($licencie, $token);

        $this->addFlash('success', sprintf('Invitation renvoyée à %s.', $licencie->getEmail()));

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
            $this->addFlash('error', 'Impossible de supprimer ce licencié : des données lui sont liées (présences, créneaux encadrés, mouvements de stock...). Désactivez plutôt son compte.');
        }

        return $this->redirectToRoute('app_licencie_index');
    }

    private function normaliserClassement(mixed $valeur): ?string
    {
        $valeur = (string) $valeur;

        return in_array($valeur, ClassementFfbad::CODES, true) ? $valeur : null;
    }
}
