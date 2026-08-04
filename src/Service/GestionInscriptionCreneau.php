<?php

namespace App\Service;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Presence;
use App\Repository\PresenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère les inscriptions à un créneau lorsqu'une capacité maximale est définie :
 * confirmation directe, liste d'attente, promotion automatique en cas de désistement
 * (avec un délai de confirmation pour la personne promue), et heure limite d'annulation.
 */
class GestionInscriptionCreneau
{
    private const DELAI_CONFIRMATION_PROMOTION = '+24 hours';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PresenceRepository $presenceRepository,
        private readonly NotificationMailer $notificationMailer,
    ) {
    }

    /**
     * @return array{ok: bool, erreur?: string}
     */
    public function repondre(Creneau $creneau, Licencie $licencie, \DateTimeImmutable $date, bool $veutVenir, bool $estBureau): array
    {
        $presence = $this->presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date);
        $etaitConfirmee = $presence && $presence->estConfirmee();

        if (!$veutVenir) {
            if ($etaitConfirmee && !$estBureau && null !== $creneau->getDelaiAnnulationHeures()) {
                $limite = $this->limiteAnnulation($creneau, $date);
                if (new \DateTimeImmutable() > $limite) {
                    return [
                        'ok' => false,
                        'erreur' => sprintf("Trop tard pour vous désinscrire : la limite était le %s.", $limite->format('d/m/Y à H:i')),
                    ];
                }
            }

            $presence = $presence ?? (new Presence())->setCreneau($creneau)->setLicencie($licencie)->setDate($date);
            $presence->setPresent(false)->setStatutInscription(null)->setPromotionExpiresAt(null);
            $this->entityManager->persist($presence);
            $this->entityManager->flush();

            if ($etaitConfirmee) {
                $this->promouvoirSuivant($creneau, $date);
            }

            return ['ok' => true];
        }

        $presence = $presence ?? (new Presence())->setCreneau($creneau)->setLicencie($licencie)->setDate($date);
        $presence->setPresent(true);

        if (null === $creneau->getCapaciteMax() || $presence->estConfirmee()) {
            $presence->setStatutInscription(Presence::STATUT_CONFIRMEE);
        } else {
            $confirmees = $this->presenceRepository->compterConfirmees($creneau, $date, $licencie);
            $presence->setStatutInscription($confirmees < $creneau->getCapaciteMax() ? Presence::STATUT_CONFIRMEE : Presence::STATUT_LISTE_ATTENTE);
        }
        $presence->setPromotionExpiresAt(null);

        $this->entityManager->persist($presence);
        $this->entityManager->flush();

        return ['ok' => true];
    }

    public function confirmerPromotion(Presence $presence): bool
    {
        if (!$presence->estEnAttenteConfirmation()) {
            return false;
        }

        $presence->setStatutInscription(Presence::STATUT_CONFIRMEE)->setPromotionExpiresAt(null);
        $this->entityManager->flush();

        return true;
    }

    public function forcerInscription(Creneau $creneau, Licencie $licencie, \DateTimeImmutable $date): Presence
    {
        $presence = $this->presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date)
            ?? (new Presence())->setCreneau($creneau)->setLicencie($licencie)->setDate($date);

        $presence->setPresent(true)->setStatutInscription(Presence::STATUT_CONFIRMEE)->setPromotionExpiresAt(null);

        $this->entityManager->persist($presence);
        $this->entityManager->flush();

        return $presence;
    }

    public function promouvoirSuivant(Creneau $creneau, \DateTimeImmutable $date): void
    {
        if (null === $creneau->getCapaciteMax()) {
            return;
        }

        if ($this->presenceRepository->compterConfirmees($creneau, $date) >= $creneau->getCapaciteMax()) {
            return;
        }

        $candidat = $this->presenceRepository->findPremierEnListeAttente($creneau, $date);
        if (!$candidat) {
            return;
        }

        $candidat->setStatutInscription(Presence::STATUT_EN_ATTENTE_CONFIRMATION);
        $candidat->setPromotionExpiresAt(new \DateTimeImmutable(self::DELAI_CONFIRMATION_PROMOTION));
        $this->entityManager->flush();

        $this->notificationMailer->promotionListeAttente($creneau, $candidat->getLicencie(), $date);
    }

    /**
     * Fait passer en liste d'attente (au bout de la file) les promotions non confirmées à temps,
     * puis tente de promouvoir la personne suivante. Destiné à être appelé périodiquement (cron).
     */
    public function expirerPromotions(): int
    {
        $expirees = $this->presenceRepository->findPromotionsExpirees(new \DateTimeImmutable());

        foreach ($expirees as $presence) {
            $creneau = $presence->getCreneau();
            $date = $presence->getDate();

            // setPresent(true) met aussi à jour "repondule", ce qui renvoie la personne en bout de file.
            $presence->setPresent(true)->setStatutInscription(Presence::STATUT_LISTE_ATTENTE)->setPromotionExpiresAt(null);
            $this->entityManager->flush();

            $this->promouvoirSuivant($creneau, $date);
        }

        return count($expirees);
    }

    private function limiteAnnulation(Creneau $creneau, \DateTimeImmutable $date): \DateTimeImmutable
    {
        $debut = $date->setTime((int) $creneau->getHeureDebut()->format('H'), (int) $creneau->getHeureDebut()->format('i'));

        return $debut->modify(sprintf('-%d hours', $creneau->getDelaiAnnulationHeures()));
    }
}
