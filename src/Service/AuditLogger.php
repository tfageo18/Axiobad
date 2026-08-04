<?php

namespace App\Service;

use App\Entity\JournalAudit;
use App\Entity\Licencie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journal d'audit des actions sensibles : paiement modifié, consultation/modification des
 * informations de santé, changement de responsable légal, suppression de compte, changement de
 * rôle, correction de stock, annulation de créneau, modification d'adhésion.
 */
class AuditLogger
{
    public const PAIEMENT_MODIFIE = 'paiement.modifie';
    public const SANTE_CONSULTEE = 'sante.consultee';
    public const SANTE_MODIFIEE = 'sante.modifiee';
    public const RESPONSABLE_LEGAL_CHANGE = 'responsable_legal.change';
    public const COMPTE_SUPPRIME = 'compte.supprime';
    public const COMPTE_ANONYMISE = 'compte.anonymise';
    public const CONSENTEMENT_SANTE_CHANGE = 'consentement_sante.change';
    public const ROLE_CHANGE = 'role.change';
    public const STOCK_CORRECTION = 'stock.correction';
    public const CRENEAU_ANNULE = 'creneau.annule';
    public const ADHESION_MODIFIEE = 'adhesion.modifiee';

    public const LIBELLES = [
        self::PAIEMENT_MODIFIE => 'Paiement modifié',
        self::SANTE_CONSULTEE => 'Informations de santé consultées',
        self::SANTE_MODIFIEE => 'Informations de santé modifiées',
        self::RESPONSABLE_LEGAL_CHANGE => 'Responsable légal modifié',
        self::COMPTE_SUPPRIME => 'Compte supprimé',
        self::COMPTE_ANONYMISE => 'Compte anonymisé',
        self::CONSENTEMENT_SANTE_CHANGE => 'Consentement santé modifié',
        self::ROLE_CHANGE => 'Rôle modifié',
        self::STOCK_CORRECTION => 'Correction de stock',
        self::CRENEAU_ANNULE => 'Créneau annulé',
        self::ADHESION_MODIFIEE => 'Adhésion modifiée',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function log(
        string $action,
        ?string $objetType = null,
        ?string $objetLibelle = null,
        ?string $ancienneValeur = null,
        ?string $nouvelleValeur = null,
    ): void {
        /** @var Licencie|null $utilisateur */
        $utilisateur = $this->security->getUser();

        $entree = (new JournalAudit())
            ->setUtilisateur($utilisateur)
            ->setAction($action)
            ->setObjetType($objetType)
            ->setObjetLibelle($objetLibelle)
            ->setAncienneValeur($ancienneValeur)
            ->setNouvelleValeur($nouvelleValeur);

        $this->entityManager->persist($entree);
        $this->entityManager->flush();
    }
}
