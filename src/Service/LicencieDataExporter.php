<?php

namespace App\Service;

use App\Entity\Licencie;
use App\Repository\AdhesionRepository;
use App\Repository\ConvocationRepository;
use App\Repository\DemandeCordageRepository;
use App\Repository\InscriptionRepository;
use App\Repository\PresenceRepository;
use App\Repository\RaquetteRepository;

/**
 * Construit l'export des données personnelles d'un licencié (droit à la portabilité, RGPD),
 * utilisé aussi bien pour l'export de ses propres données que pour l'export par un responsable
 * légal des données de l'enfant dont il a la charge.
 */
class LicencieDataExporter
{
    public function __construct(
        private readonly PresenceRepository $presenceRepository,
        private readonly ConvocationRepository $convocationRepository,
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly DemandeCordageRepository $demandeCordageRepository,
        private readonly RaquetteRepository $raquetteRepository,
        private readonly AdhesionRepository $adhesionRepository,
    ) {
    }

    public function exporter(Licencie $licencie): array
    {
        return [
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
                $this->presenceRepository->findBy(['licencie' => $licencie])
            ),
            'convocationsInterclubs' => array_map(
                static fn ($c) => [
                    'journee' => $c->getRencontre()?->getJournee(),
                    'adversaire' => $c->getRencontre()?->getAdversaire(),
                    'present' => $c->isPresent(),
                ],
                $this->convocationRepository->findBy(['licencie' => $licencie])
            ),
            'inscriptionsEvenements' => array_map(
                static fn ($i) => [
                    'evenement' => $i->getEvenement()?->getTitre(),
                    'statut' => $i->getStatut(),
                ],
                $this->inscriptionRepository->findBy(['licencie' => $licencie])
            ),
            'raquettes' => array_map(
                static fn ($r) => [
                    'marque' => $r->getMarque(),
                    'modele' => $r->getModele(),
                    'tensionHabituelle' => $r->getTensionHabituelle(),
                ],
                $this->raquetteRepository->findBy(['licencie' => $licencie])
            ),
            'demandesCordage' => array_map(
                static fn ($d) => [
                    'statut' => $d->getStatut(),
                    'dateDepot' => $d->getDateDepot()->format('Y-m-d'),
                    'tension' => $d->getTension(),
                ],
                $this->demandeCordageRepository->findBy(['licencie' => $licencie])
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
                $this->adhesionRepository->findBy(['licencie' => $licencie])
            ),
        ];
    }
}
