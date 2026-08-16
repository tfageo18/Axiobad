<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\Creneau;
use App\Entity\LienFamilial;
use App\Entity\Licencie;
use App\Entity\PaiementAdhesion;
use App\Repository\AdhesionRepository;
use App\Repository\CreneauExceptionRepository;
use App\Repository\CreneauRepository;
use App\Repository\LicencieRepository;
use App\Repository\LienFamilialRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Service\AuditLogger;
use App\Service\GestionInscriptionCreneau;
use App\Service\LicencieDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ma-famille')]
class FamilleController extends AbstractController
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    #[Route('', name: 'app_famille_index', methods: ['GET'])]
    public function index(
        LicencieRepository $licencieRepository,
        CreneauRepository $creneauRepository,
        PresenceRepository $presenceRepository,
        SaisonRepository $saisonRepository,
        AdhesionRepository $adhesionRepository,
        CreneauExceptionRepository $exceptionRepository,
        LienFamilialRepository $lienFamilialRepository,
    ): Response {
        /** @var Licencie $responsable */
        $responsable = $this->getUser();

        $enfants = array_values(array_filter(
            $licencieRepository->findAll(),
            static fn (Licencie $l) => $responsable->estResponsableDe($l)
        ));

        $saisonEnCours = $saisonRepository->findEnCours();
        $creneauxActifs = array_values(array_filter($creneauRepository->findAll(), static fn (Creneau $c) => $c->isActif()));

        $fiches = [];
        foreach ($enfants as $enfant) {
            $fiches[] = [
                'enfant' => $enfant,
                'prochainsCreneaux' => $this->calculerProchainsCreneaux($enfant, $creneauxActifs, $presenceRepository, $exceptionRepository),
                'adhesion' => $saisonEnCours ? $adhesionRepository->findOneByLicencieEtSaison($enfant, $saisonEnCours) : null,
            ];
        }

        // Comptes "pour lesquels je peux agir" (moi-même + les mineurs dont je suis responsable
        // légal) : sert à la fois à afficher les demandes reçues pour eux et à proposer de créer
        // un lien en leur nom.
        $comptesGeres = array_merge([$responsable], $enfants);

        $liensVus = [];
        $demandesRecues = [];
        $liensAcceptes = [];
        foreach ($comptesGeres as $compte) {
            foreach ($lienFamilialRepository->findPourLicencie($compte) as $lien) {
                if (isset($liensVus[$lien->getId()])) {
                    continue;
                }
                $liensVus[$lien->getId()] = true;

                $autrePersonne = $lien->getAutrePersonne($compte);
                if (!$autrePersonne) {
                    continue;
                }

                if ($lien->estEnAttente() && $lien->getCible() === $compte) {
                    $demandesRecues[] = ['lien' => $lien, 'pour' => $compte, 'autrePersonne' => $autrePersonne];
                } elseif ($lien->estAccepte()) {
                    $liensAcceptes[] = [
                        'lien' => $lien,
                        'pour' => $compte,
                        'autrePersonne' => $autrePersonne,
                        'prochainsCreneaux' => $this->calculerProchainsCreneaux($autrePersonne, $creneauxActifs, $presenceRepository, $exceptionRepository),
                        'adhesion' => $saisonEnCours ? $adhesionRepository->findOneByLicencieEtSaison($autrePersonne, $saisonEnCours) : null,
                    ];
                }
            }
        }

        $demandesEnvoyees = array_values(array_filter(
            $lienFamilialRepository->findPourLicencie($responsable),
            static fn (LienFamilial $l) => $l->estEnAttente() && $l->getDemandeur() === $responsable
        ));

        $licenciesLiables = array_values(array_filter(
            $licencieRepository->findBy([], ['nom' => 'ASC']),
            fn (Licencie $l) => !in_array($l, $comptesGeres, true)
        ));

        return $this->render('famille/index.html.twig', [
            'fiches' => $fiches,
            'saisonEnCours' => $saisonEnCours,
            'comptesGeres' => $comptesGeres,
            'demandesRecues' => $demandesRecues,
            'demandesEnvoyees' => $demandesEnvoyees,
            'liensAcceptes' => $liensAcceptes,
            'licenciesLiables' => $licenciesLiables,
            'typesLien' => LienFamilial::TYPES,
        ]);
    }

    #[Route('/liens', name: 'app_famille_lien_new', methods: ['POST'])]
    public function nouveauLien(Request $request, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository, LienFamilialRepository $lienFamilialRepository, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('lien-familial-new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_famille_index');
        }

        /** @var Licencie $responsable */
        $responsable = $this->getUser();

        $demandeurId = $request->request->get('demandeur');
        $demandeur = $demandeurId ? $licencieRepository->find($demandeurId) : $responsable;
        if (!$demandeur || (!$responsable->estResponsableDe($demandeur) && $demandeur !== $responsable)) {
            $this->addFlash('error', "Vous ne pouvez pas créer de lien au nom de ce compte.");

            return $this->redirectToRoute('app_famille_index');
        }

        $cible = $licencieRepository->find($request->request->get('cible'));
        $typeLien = (string) $request->request->get('typeLien');

        if (!$cible || !array_key_exists($typeLien, LienFamilial::TYPES)) {
            $this->addFlash('error', 'Sélection invalide.');

            return $this->redirectToRoute('app_famille_index');
        }

        if ($cible === $demandeur) {
            $this->addFlash('error', 'Impossible de créer un lien avec soi-même.');

            return $this->redirectToRoute('app_famille_index');
        }

        if ($lienFamilialRepository->existeEntre($demandeur, $cible)) {
            $this->addFlash('error', sprintf('Un lien existe déjà (ou est en attente) avec %s.', $cible->getNomComplet()));

            return $this->redirectToRoute('app_famille_index');
        }

        $lien = (new LienFamilial())
            ->setDemandeur($demandeur)
            ->setCible($cible)
            ->setTypeLien($typeLien);

        $entityManager->persist($lien);
        $entityManager->flush();

        $auditLogger->log(
            AuditLogger::LIEN_FAMILIAL_CHANGE,
            'LienFamilial',
            sprintf('%s ↔ %s', $demandeur->getNomComplet(), $cible->getNomComplet()),
            null,
            sprintf('Demande envoyée (%s)', $lien->getTypeLienLabel())
        );

        $this->addFlash('success', sprintf('Demande de lien familial envoyée à %s, en attente de son accord.', $cible->getNomComplet()));

        return $this->redirectToRoute('app_famille_index');
    }

    #[Route('/liens/{id}/accepter', name: 'app_famille_lien_accepter', methods: ['POST'])]
    public function accepterLien(Request $request, LienFamilial $lien, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('lien-familial-accepter-'.$lien->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_famille_index');
        }

        if (!$this->peutAgirPour($lien->getCible()) || !$lien->estEnAttente()) {
            throw $this->createAccessDeniedException();
        }

        $lien->accepter();
        $entityManager->flush();

        $auditLogger->log(
            AuditLogger::LIEN_FAMILIAL_CHANGE,
            'LienFamilial',
            sprintf('%s ↔ %s', $lien->getDemandeur()->getNomComplet(), $lien->getCible()->getNomComplet()),
            'En attente',
            'Accepté'
        );

        $this->addFlash('success', sprintf('Lien familial avec %s accepté.', $lien->getDemandeur()->getNomComplet()));

        return $this->redirectToRoute('app_famille_index');
    }

    #[Route('/liens/{id}/refuser', name: 'app_famille_lien_refuser', methods: ['POST'])]
    public function refuserLien(Request $request, LienFamilial $lien, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('lien-familial-refuser-'.$lien->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_famille_index');
        }

        if (!$this->peutAgirPour($lien->getCible()) || !$lien->estEnAttente()) {
            throw $this->createAccessDeniedException();
        }

        $auditLogger->log(
            AuditLogger::LIEN_FAMILIAL_CHANGE,
            'LienFamilial',
            sprintf('%s ↔ %s', $lien->getDemandeur()->getNomComplet(), $lien->getCible()->getNomComplet()),
            'En attente',
            'Refusé'
        );

        $entityManager->remove($lien);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de lien familial refusée.');

        return $this->redirectToRoute('app_famille_index');
    }

    #[Route('/liens/{id}/retirer', name: 'app_famille_lien_retirer', methods: ['POST'])]
    public function retirerLien(Request $request, LienFamilial $lien, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('lien-familial-retirer-'.$lien->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_famille_index');
        }

        if (!$this->peutAgirPour($lien->getDemandeur()) && !$this->peutAgirPour($lien->getCible()) && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }

        $auditLogger->log(
            AuditLogger::LIEN_FAMILIAL_CHANGE,
            'LienFamilial',
            sprintf('%s ↔ %s', $lien->getDemandeur()->getNomComplet(), $lien->getCible()->getNomComplet()),
            $lien->getStatut(),
            'Retiré'
        );

        $entityManager->remove($lien);
        $entityManager->flush();

        $this->addFlash('success', 'Lien familial retiré.');

        return $this->redirectToRoute('app_famille_index');
    }

    /**
     * L'utilisateur connecté peut-il agir "pour" ce compte : soit lui-même, soit son
     * responsable légal (si mineur), soit le bureau.
     */
    private function peutAgirPour(?Licencie $compte): bool
    {
        if (!$compte) {
            return false;
        }
        if ($this->isGranted('ROLE_BUREAU')) {
            return true;
        }

        /** @var Licencie $utilisateur */
        $utilisateur = $this->getUser();

        return $utilisateur === $compte || $utilisateur->estResponsableDe($compte);
    }

    #[Route('/{id}/creneaux/{creneauId}/presence', name: 'app_famille_presence', methods: ['POST'])]
    public function presence(Request $request, Licencie $enfant, int $creneauId, CreneauRepository $creneauRepository, GestionInscriptionCreneau $gestionInscriptionCreneau): Response
    {
        $this->refuserSiPasResponsable($enfant);

        $creneau = $creneauRepository->find($creneauId);
        if (!$creneau) {
            return $this->redirectToRoute('app_famille_index');
        }

        $date = new \DateTimeImmutable((string) $request->request->get('date'));
        $veutVenir = '1' === $request->request->get('present');

        $resultat = $gestionInscriptionCreneau->repondre($creneau, $enfant, $date, $veutVenir, false);

        if (!$resultat['ok']) {
            $this->addFlash('error', $resultat['erreur']);
        } else {
            $this->addFlash('success', 'Réponse enregistrée pour '.$enfant->getNomComplet().'.');
        }

        return $this->redirectToRoute('app_famille_index');
    }

    #[Route('/{id}/adhesion', name: 'app_famille_adhesion', methods: ['GET', 'POST'])]
    public function adhesion(Request $request, Licencie $enfant, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository): Response
    {
        $this->refuserSiPasResponsable($enfant);

        $saisonId = $request->query->get('saison') ?? $request->request->get('saison');
        $saison = $saisonId ? $saisonRepository->find($saisonId) : $saisonRepository->findEnCours();
        if (!$saison) {
            $saison = $saisonRepository->findAllTrieesParDate()[0] ?? null;
        }
        if (!$saison) {
            $this->addFlash('error', "Aucune saison n'est configurée.");

            return $this->redirectToRoute('app_famille_index');
        }

        return $this->render('famille/adhesion.html.twig', [
            'enfant' => $enfant,
            'saison' => $saison,
            'saisons' => $saisonRepository->findAllTrieesParDate(),
            'adhesion' => $adhesionRepository->findOneByLicencieEtSaison($enfant, $saison),
        ]);
    }

    #[Route('/{id}/mes-donnees', name: 'app_famille_export', methods: ['GET'])]
    public function exporterDonnees(Licencie $enfant, LicencieDataExporter $exporter): JsonResponse
    {
        $this->refuserSiPasResponsable($enfant);

        $response = new JsonResponse($exporter->exporter($enfant), 200, [], false);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="donnees-%s-axiobad.json"', mb_strtolower(str_replace(' ', '-', $enfant->getNomComplet()))));

        return $response;
    }

    #[Route('/{id}/adhesion/paiements', name: 'app_famille_adhesion_paiement_new', methods: ['POST'])]
    public function ajouterPaiement(Request $request, Licencie $enfant, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository): Response
    {
        $this->refuserSiPasResponsable($enfant);

        $saison = $saisonRepository->find($request->request->get('saison'));
        if (!$saison) {
            $this->addFlash('error', 'Saison invalide.');

            return $this->redirectToRoute('app_famille_index');
        }

        $adhesion = $adhesionRepository->findOneByLicencieEtSaison($enfant, $saison)
            ?? (new Adhesion())->setLicencie($enfant)->setSaison($saison);

        $montant = (float) str_replace(',', '.', (string) $request->request->get('montant'));
        $moyen = (string) $request->request->get('moyen');
        if (!array_key_exists($moyen, PaiementAdhesion::MOYENS)) {
            $moyen = PaiementAdhesion::MOYEN_ESPECES;
        }
        $dateRaw = (string) $request->request->get('date');

        if ($montant <= 0) {
            $this->addFlash('error', 'Le montant du versement doit être supérieur à zéro.');

            return $this->redirectToRoute('app_famille_adhesion', ['id' => $enfant->getId(), 'saison' => $saison->getId()]);
        }

        $paiement = (new PaiementAdhesion())
            ->setAdhesion($adhesion)
            ->setMontant($montant)
            ->setDate($dateRaw ? new \DateTimeImmutable($dateRaw) : new \DateTimeImmutable())
            ->setMoyen($moyen)
            ->setNumeroCheque(PaiementAdhesion::MOYEN_CHEQUE === $moyen ? ((string) $request->request->get('numeroCheque') ?: null) : null);

        $entityManager->persist($adhesion);
        $entityManager->persist($paiement);

        if (null !== $adhesion->getMontantTotal() && $adhesion->getMontantRestant() - $montant <= 0) {
            $adhesion->setStatut(Adhesion::STATUT_PAYEE);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Versement enregistré.');

        return $this->redirectToRoute('app_famille_adhesion', ['id' => $enfant->getId(), 'saison' => $saison->getId()]);
    }

    /**
     * @param Creneau[] $creneauxActifs
     *
     * @return array<int, array{creneau: Creneau, date: \DateTimeImmutable, presence: mixed}>
     */
    private function calculerProchainsCreneaux(Licencie $enfant, array $creneauxActifs, PresenceRepository $presenceRepository, CreneauExceptionRepository $exceptionRepository): array
    {
        $creneauxCorrespondants = array_values(array_filter($creneauxActifs, static fn (Creneau $c) => $c->correspondA($enfant)));

        $prochains = [];
        $aujourdhui = new \DateTimeImmutable('today');
        for ($i = 0; $i < 14; ++$i) {
            $date = $aujourdhui->modify(sprintf('+%d days', $i));
            $nomJour = self::JOURS[((int) $date->format('N')) - 1];

            foreach ($creneauxCorrespondants as $creneau) {
                if ($creneau->getJourSemaine() !== $nomJour) {
                    continue;
                }
                if ($creneau->getRecurrenceDebut() && $date < $creneau->getRecurrenceDebut()) {
                    continue;
                }
                if ($creneau->getRecurrenceFin() && $date > $creneau->getRecurrenceFin()) {
                    continue;
                }

                $exception = $exceptionRepository->findOneByCreneauEtDate($creneau, $date);
                if ($exception && $exception->estAnnulee()) {
                    continue;
                }

                $prochains[] = [
                    'creneau' => $creneau,
                    'date' => $date,
                    'exception' => $exception,
                    'presence' => $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $enfant, $date),
                ];
            }
        }
        usort($prochains, static fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return array_slice($prochains, 0, 5);
    }

    private function refuserSiPasResponsable(Licencie $enfant): void
    {
        /** @var Licencie $responsable */
        $responsable = $this->getUser();

        if (!$responsable->estResponsableDe($enfant) && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }
    }
}
