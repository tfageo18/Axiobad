<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\CommunicationEnvoi;
use App\Entity\Creneau;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Entity\ModeleCommunication;
use App\Repository\AdhesionRepository;
use App\Repository\CommunicationEnvoiRepository;
use App\Repository\CreneauRepository;
use App\Repository\EquipeRepository;
use App\Repository\EvenementRepository;
use App\Repository\LicencieRepository;
use App\Repository\ModeleCommunicationRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/communications')]
#[IsGranted('ROLE_BUREAU')]
class CommunicationController extends AbstractController
{
    public function __construct(
        private readonly LicencieRepository $licencieRepository,
        private readonly EquipeRepository $equipeRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly AdhesionRepository $adhesionRepository,
        private readonly SaisonRepository $saisonRepository,
        private readonly PresenceRepository $presenceRepository,
        private readonly CommunicationEnvoiRepository $envoiRepository,
        private readonly ModeleCommunicationRepository $modeleRepository,
    ) {
    }

    #[Route('', name: 'app_communication_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cible = (string) $request->query->get('cible', '');
        $resolution = $cible ? $this->resoudreCible($cible) : null;

        return $this->render('communication/index.html.twig', [
            'equipes' => $this->equipeRepository->findAll(),
            'creneaux' => array_values(array_filter($this->creneauRepository->findAll(), static fn (Creneau $c) => $c->isActif())),
            'evenements' => $this->evenementRepository->findAll(),
            'cibleChoisie' => $cible,
            'destinataires' => $resolution['licencies'] ?? null,
            'cibleLibelle' => $resolution['libelle'] ?? null,
            'envois' => $this->envoiRepository->findRecentes(),
            'modeles' => $this->modeleRepository->findAllTries(),
        ]);
    }

    #[Route('/envoyer', name: 'app_communication_envoyer', methods: ['POST'])]
    public function envoyer(Request $request, NotificationMailer $notificationMailer, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$this->isCsrfTokenValid('communication-envoyer', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_communication_index');
        }

        $cible = (string) $request->request->get('cible', '');
        $sujet = trim((string) $request->request->get('sujet'));
        $corps = trim((string) $request->request->get('corps'));

        if ('' === $cible || '' === $sujet || '' === $corps) {
            $this->addFlash('error', 'Cible, sujet et message sont obligatoires.');

            return $this->redirectToRoute('app_communication_index', ['cible' => $cible]);
        }

        $resolution = $this->resoudreCible($cible);
        $licencies = $resolution['licencies'];

        if (0 === count($licencies)) {
            $this->addFlash('error', 'Aucun destinataire pour cette cible.');

            return $this->redirectToRoute('app_communication_index', ['cible' => $cible]);
        }

        // Programmation différée : si une date/heure future est indiquée, la communication est
        // enregistrée en attente et envoyée plus tard par la commande planifiée
        // app:communication:envoyer-planifiees (cron), pas immédiatement.
        $dateEnvoi = trim((string) $request->request->get('date_envoi'));
        $heureEnvoi = trim((string) $request->request->get('heure_envoi'));
        $planifiePour = null;
        if ($dateEnvoi && $heureEnvoi) {
            try {
                $candidat = new \DateTimeImmutable($dateEnvoi.' '.$heureEnvoi);
                if ($candidat > new \DateTimeImmutable()) {
                    $planifiePour = $candidat;
                }
            } catch (\Exception) {
                // date/heure invalide, ignorée : envoi immédiat
            }
        }

        [$pieceJointeChemin, $pieceJointeNom] = $this->traiterPieceJointe($request, $slugger);

        /** @var Licencie $auteur */
        $auteur = $this->getUser();

        $envoi = (new CommunicationEnvoi())
            ->setSujet($sujet)
            ->setCorps($corps)
            ->setCibleLibelle($resolution['libelle'])
            ->setNombreDestinataires(count($licencies))
            ->setAuteur($auteur)
            ->setDestinatairesIds(array_map(static fn (Licencie $l) => $l->getId(), $licencies))
            ->setPieceJointeChemin($pieceJointeChemin)
            ->setPieceJointeNom($pieceJointeNom);

        if ($planifiePour) {
            $envoi->setStatut(CommunicationEnvoi::STATUT_EN_ATTENTE)->setPlanifiePour($planifiePour);
            $entityManager->persist($envoi);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Message programmé pour le %s à %d destinataire(s).', $planifiePour->format('d/m/Y à H:i'), count($licencies)));

            return $this->redirectToRoute('app_communication_index');
        }

        $echecs = [];
        foreach ($licencies as $destinataire) {
            if (!$notificationMailer->communicationCiblee($destinataire, $sujet, $corps, $pieceJointeChemin, $pieceJointeNom)) {
                $echecs[] = $destinataire->getEmail() ?? sprintf('#%d', $destinataire->getId());
            }
        }

        $envoi->setNombreEchecs(count($echecs))->setEmailsEnEchec($echecs ? implode(', ', $echecs) : null);

        $entityManager->persist($envoi);
        $entityManager->flush();

        if ($echecs) {
            $this->addFlash('error', sprintf('Envoyé à %d destinataire(s), %d échec(s) : %s.', count($licencies) - count($echecs), count($echecs), implode(', ', $echecs)));
        } else {
            $this->addFlash('success', sprintf('Message envoyé à %d destinataire(s).', count($licencies)));
        }

        return $this->redirectToRoute('app_communication_index');
    }

    #[Route('/{id}/annuler', name: 'app_communication_annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(CommunicationEnvoi $envoi, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('communication-annuler-'.$envoi->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_communication_index');
        }

        if (!$envoi->estEnAttente()) {
            $this->addFlash('error', 'Cette communication a déjà été envoyée ou annulée.');

            return $this->redirectToRoute('app_communication_index');
        }

        $envoi->setStatut(CommunicationEnvoi::STATUT_ANNULE);
        $entityManager->flush();

        $this->addFlash('success', 'Envoi programmé annulé.');

        return $this->redirectToRoute('app_communication_index');
    }

    #[Route('/modeles', name: 'app_communication_modele_creer', methods: ['POST'])]
    public function creerModele(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('communication-modele-creer', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_communication_index');
        }

        $nom = trim((string) $request->request->get('nom'));
        $sujet = trim((string) $request->request->get('sujet'));
        $corps = trim((string) $request->request->get('corps'));

        if ('' === $nom || '' === $sujet || '' === $corps) {
            $this->addFlash('error', 'Nom, sujet et message sont obligatoires pour enregistrer un modèle.');

            return $this->redirectToRoute('app_communication_index');
        }

        $modele = (new ModeleCommunication())->setNom($nom)->setSujet($sujet)->setCorps($corps);
        $entityManager->persist($modele);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Modèle « %s » enregistré.', $nom));

        return $this->redirectToRoute('app_communication_index');
    }

    #[Route('/modeles/{id}/supprimer', name: 'app_communication_modele_supprimer', methods: ['POST'])]
    public function supprimerModele(ModeleCommunication $modele, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('communication-modele-supprimer-'.$modele->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_communication_index');
        }

        $entityManager->remove($modele);
        $entityManager->flush();

        $this->addFlash('success', 'Modèle supprimé.');

        return $this->redirectToRoute('app_communication_index');
    }

    /**
     * @return array{0: ?string, 1: ?string} chemin absolu sur le disque et nom original du fichier
     */
    private function traiterPieceJointe(Request $request, SluggerInterface $slugger): array
    {
        $fichier = $request->files->get('piece_jointe');
        if (!$fichier) {
            return [null, null];
        }

        if (!$fichier->isValid()) {
            $this->addFlash('error', sprintf("La pièce jointe n'a pas pu être envoyée (%s).", $fichier->getErrorMessage()));

            return [null, null];
        }

        $nomOriginal = $fichier->getClientOriginalName();
        $nomSansExtension = pathinfo($nomOriginal, PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($nomSansExtension);
        $nomFichier = sprintf('%s-%s.%s', uniqid(), $safeFilename, $fichier->guessExtension() ?? 'bin');

        try {
            $uploadsDir = $this->getParameter('kernel.project_dir').'/var/uploads/communications';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $fichier->move($uploadsDir, $nomFichier);

            return [$uploadsDir.'/'.$nomFichier, $nomOriginal];
        } catch (FileException) {
            $this->addFlash('error', "Erreur lors de l'envoi de la pièce jointe.");

            return [null, null];
        }
    }

    /**
     * @return array{licencies: Licencie[], libelle: string}
     */
    private function resoudreCible(string $cible): array
    {
        [$type, $param] = array_pad(explode(':', $cible, 2), 2, null);

        return match ($type) {
            'TOUS' => [
                'licencies' => array_values(array_filter(
                    $this->licencieRepository->findAll(),
                    static fn (Licencie $l) => $l->aUnCompte() && $l->isActif()
                )),
                'libelle' => 'Tous les licenciés',
            ],
            'EQUIPE' => $this->cibleEquipe((int) $param),
            'CRENEAU' => $this->cibleCreneau((int) $param),
            'EVENEMENT' => $this->cibleEvenement((int) $param),
            'IMPAYES' => $this->cibleImpayes(),
            'NON_REPONDANTS' => $this->cibleNonRepondants((int) $param),
            'RESPONSABLES_LEGAUX' => $this->cibleResponsablesLegaux(),
            'CATEGORIE' => $this->cibleCategorie((string) $param),
            default => ['licencies' => [], 'libelle' => 'Cible inconnue'],
        };
    }

    private function cibleEquipe(int $id): array
    {
        $equipe = $this->equipeRepository->find($id);
        if (!$equipe) {
            return ['licencies' => [], 'libelle' => 'Équipe introuvable'];
        }

        $licencies = array_values(array_filter(
            $equipe->getMembres()->toArray(),
            static fn (Licencie $l) => $l->aUnCompte() && $l->isActif()
        ));

        return ['licencies' => $licencies, 'libelle' => sprintf('Équipe « %s »', $equipe->getNom())];
    }

    private function cibleCreneau(int $id): array
    {
        $creneau = $this->creneauRepository->find($id);
        if (!$creneau) {
            return ['licencies' => [], 'libelle' => 'Créneau introuvable'];
        }

        $licencies = array_values(array_filter(
            $this->licencieRepository->findAll(),
            static fn (Licencie $l) => $l->aUnCompte() && $l->isActif() && $creneau->correspondA($l)
        ));

        return ['licencies' => $licencies, 'libelle' => sprintf('Licenciés du créneau « %s »', $creneau->getNom())];
    }

    private function cibleEvenement(int $id): array
    {
        $evenement = $this->evenementRepository->find($id);
        if (!$evenement) {
            return ['licencies' => [], 'libelle' => 'Évènement introuvable'];
        }

        $licencies = [];
        foreach ($evenement->getInscriptions() as $inscription) {
            if (Inscription::STATUT_CONFIRMEE === $inscription->getStatut() && $inscription->getLicencie()?->aUnCompte()) {
                $licencies[] = $inscription->getLicencie();
            }
        }

        return ['licencies' => $licencies, 'libelle' => sprintf('Participants à « %s »', $evenement->getTitre())];
    }

    private function cibleImpayes(): array
    {
        $saisonEnCours = $this->saisonRepository->findEnCours();
        if (!$saisonEnCours) {
            return ['licencies' => [], 'libelle' => 'Impayés (aucune saison en cours)'];
        }

        $licencies = [];
        foreach ($this->adhesionRepository->findBy(['saison' => $saisonEnCours]) as $adhesion) {
            if (!$adhesion->isPayee() && Adhesion::STATUT_EXONEREE !== $adhesion->getStatut() && $adhesion->getLicencie()?->aUnCompte()) {
                $licencies[] = $adhesion->getLicencie();
            }
        }

        return ['licencies' => $licencies, 'libelle' => sprintf('Adhésions impayées — %s', $saisonEnCours->getLibelle())];
    }

    private function cibleNonRepondants(int $creneauId): array
    {
        $creneau = $this->creneauRepository->find($creneauId);
        if (!$creneau) {
            return ['licencies' => [], 'libelle' => 'Créneau introuvable'];
        }

        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $date = new \DateTimeImmutable('today');
        for ($i = 0; $i < 14; ++$i) {
            if ($jours[((int) $date->format('N')) - 1] === $creneau->getJourSemaine()) {
                break;
            }
            $date = $date->modify('+1 day');
        }

        $licencies = array_values(array_filter(
            $this->licencieRepository->findAll(),
            fn (Licencie $l) => $l->aUnCompte() && $l->isActif() && $creneau->correspondA($l)
                && !$this->presenceRepository->findOneByCreneauLicencieEtDate($creneau, $l, $date)
        ));

        return ['licencies' => $licencies, 'libelle' => sprintf('Non-répondants « %s » du %s', $creneau->getNom(), $date->format('d/m/Y'))];
    }

    private function cibleResponsablesLegaux(): array
    {
        $tous = $this->licencieRepository->findAll();
        $licencies = array_values(array_filter(
            $tous,
            static fn (Licencie $l) => $l->aUnCompte() && $l->isActif() && array_any($tous, static fn (Licencie $enfant) => $l->estResponsableDe($enfant))
        ));

        return ['licencies' => $licencies, 'libelle' => 'Responsables légaux'];
    }

    private function cibleCategorie(string $valeur): array
    {
        $licencies = array_values(array_filter(
            $this->licencieRepository->findAll(),
            static fn (Licencie $l) => $l->aUnCompte() && $l->isActif() && $l->getCategorie() === $valeur
        ));

        $libelle = Creneau::CATEGORIE_ADULTE === $valeur ? 'Adultes' : 'Enfants';

        return ['licencies' => $licencies, 'libelle' => $libelle];
    }
}
