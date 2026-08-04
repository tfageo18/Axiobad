<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\CommunicationEnvoi;
use App\Entity\Creneau;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Repository\AdhesionRepository;
use App\Repository\CommunicationEnvoiRepository;
use App\Repository\CreneauRepository;
use App\Repository\EquipeRepository;
use App\Repository\EvenementRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Repository\SaisonRepository;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        ]);
    }

    #[Route('/envoyer', name: 'app_communication_envoyer', methods: ['POST'])]
    public function envoyer(Request $request, NotificationMailer $notificationMailer, EntityManagerInterface $entityManager): Response
    {
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

        $echecs = [];
        foreach ($licencies as $destinataire) {
            if (!$notificationMailer->communicationCiblee($destinataire, $sujet, $corps)) {
                $echecs[] = $destinataire->getEmail() ?? sprintf('#%d', $destinataire->getId());
            }
        }

        /** @var Licencie $auteur */
        $auteur = $this->getUser();

        $envoi = (new CommunicationEnvoi())
            ->setSujet($sujet)
            ->setCorps($corps)
            ->setCibleLibelle($resolution['libelle'])
            ->setNombreDestinataires(count($licencies))
            ->setNombreEchecs(count($echecs))
            ->setEmailsEnEchec($echecs ? implode(', ', $echecs) : null)
            ->setAuteur($auteur);

        $entityManager->persist($envoi);
        $entityManager->flush();

        if ($echecs) {
            $this->addFlash('error', sprintf('Envoyé à %d destinataire(s), %d échec(s) : %s.', count($licencies) - count($echecs), count($echecs), implode(', ', $echecs)));
        } else {
            $this->addFlash('success', sprintf('Message envoyé à %d destinataire(s).', count($licencies)));
        }

        return $this->redirectToRoute('app_communication_index');
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
