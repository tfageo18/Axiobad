<?php

namespace App\EventSubscriber;

use App\Entity\Licencie;
use App\Security\PasswordStrengthChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Revérifie périodiquement (au plus une fois tous les 7 jours, à la connexion) que le mot de
 * passe d'un licencié n'apparaît pas dans une base de fuites de données connues.
 *
 * Cette revérification à chaque connexion — plutôt qu'une seule fois à la création du mot de
 * passe — rattrape deux cas que PasswordStrengthChecker seul ne peut pas couvrir : un mot de
 * passe accepté sans vérification car le service externe était injoignable à ce moment-là
 * (fail-open), et un mot de passe qui devient compromis après coup (fuite sur un autre site,
 * découverte ultérieurement).
 *
 * Le mot de passe en clair n'est disponible qu'un court instant pendant l'authentification (il
 * est ensuite irrémédiablement effacé) : on l'intercepte donc ici, juste après que Symfony a
 * vérifié qu'il est correct — CheckCredentialsListener (priorité 0) doit avoir déjà validé le mot
 * de passe et placé un PasswordUpgradeBadge (utilisé nativement par Symfony pour le réhachage
 * automatique) avant que ce listener (priorité -5) ne s'exécute. Le badge est consommé puis
 * immédiatement recréé à l'identique, pour ne pas perturber ce mécanisme de réhachage.
 */
class PasswordExposureSubscriber implements EventSubscriberInterface
{
    private const DELAI_ENTRE_VERIFICATIONS = '-7 days';

    public function __construct(
        private readonly PasswordStrengthChecker $passwordStrengthChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['onCheckPassport', -5]];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        if (!$passport->hasBadge(PasswordCredentials::class) || !$passport->hasBadge(PasswordUpgradeBadge::class)) {
            return;
        }

        $licencie = $passport->getUser();
        if (!$licencie instanceof Licencie) {
            return;
        }

        $derniereVerification = $licencie->getMotDePasseVerifieLe();
        if (null !== $derniereVerification && $derniereVerification > new \DateTimeImmutable(self::DELAI_ENTRE_VERIFICATIONS)) {
            return;
        }

        /** @var PasswordUpgradeBadge $badge */
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $motDePasse = $badge->getAndErasePlaintextPassword();
        // On remet immédiatement un badge intact, pour ne pas priver le réhachage automatique
        // de Symfony (PasswordMigratingListener, exécuté plus tard sur LoginSuccessEvent) du mot
        // de passe en clair dont il a besoin.
        $passport->addBadge(new PasswordUpgradeBadge($motDePasse, $badge->getPasswordUpgrader()));

        if ('' === $motDePasse) {
            return;
        }

        $expose = $this->passwordStrengthChecker->estExpose($motDePasse);
        if (null === $expose) {
            // Service injoignable : on retentera à la prochaine connexion, pas de mise à jour.
            return;
        }

        $licencie->setMotDePasseExpose($expose);
        $licencie->setMotDePasseVerifieLe(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
