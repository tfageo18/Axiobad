<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\PaiementAdhesion;
use App\Repository\AdhesionRepository;
use App\Repository\CreneauExceptionRepository;
use App\Repository\CreneauRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
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

        return $this->render('famille/index.html.twig', [
            'fiches' => $fiches,
            'saisonEnCours' => $saisonEnCours,
        ]);
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
