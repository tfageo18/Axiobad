<?php

namespace App\Service;

use App\Entity\Adhesion;
use App\Entity\Creneau;
use App\Entity\DemandeCordage;
use App\Entity\Evenement;
use App\Entity\Licencie;
use App\Entity\NotificationEnvoyee;
use App\Entity\RencontreInterclub;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoie les emails de notification (rappels, alertes) aux licenciés. Les échecs d'envoi sont
 * journalisés mais ne lèvent jamais d'exception — un email non envoyé ne doit jamais faire
 * planter une commande ou une action utilisateur.
 */
class NotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $mailerFrom,
        private readonly LoggerInterface $logger,
        private readonly PushNotifier $pushNotifier,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function cordagePret(DemandeCordage $demande): bool
    {
        $lieu = $demande->getLieuRetour() ? sprintf(' (%s)', $demande->getLieuRetour()) : '';
        $url = $this->urlGenerator->generate('app_cordage_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $demande->getLicencie(),
            'Votre raquette est prête',
            sprintf(
                "Bonjour %s,\n\nVotre raquette est prête à être récupérée%s.\n\nRetrouvez le détail sur Axiobad : %s",
                $demande->getLicencie()->getPrenom(),
                $lieu,
                $url
            ),
            url: $url
        );
    }

    public function promotionListeAttente(Creneau $creneau, Licencie $licencie, \DateTimeImmutable $date): bool
    {
        $url = $this->urlGenerator->generate('app_calendrier', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            sprintf('Une place s\'est libérée — %s', $creneau->getNom()),
            sprintf(
                "Bonjour %s,\n\nUne place s'est libérée pour le créneau « %s » du %s.\n\nVous devez confirmer votre venue sous 24h sur Axiobad, sans quoi la place sera proposée à la personne suivante : %s",
                $licencie->getPrenom(),
                $creneau->getNom(),
                $date->format('d/m/Y'),
                $url
            ),
            url: $url
        );
    }

    public function rappelReponseCreneau(Licencie $licencie, Creneau $creneau, \DateTimeImmutable $date): bool
    {
        $url = $this->urlGenerator->generate('app_calendrier', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            sprintf('Pensez à répondre — %s le %s', $creneau->getNom(), $date->format('d/m')),
            sprintf(
                "Bonjour %s,\n\nVous n'avez pas encore répondu pour le créneau « %s » du %s. Indiquez votre présence sur Axiobad : %s",
                $licencie->getPrenom(),
                $creneau->getNom(),
                $date->format('d/m/Y'),
                $url
            ),
            url: $url
        );
    }

    public function promotionBientotExpiree(Licencie $licencie, Creneau $creneau, \DateTimeImmutable $date): bool
    {
        $url = $this->urlGenerator->generate('app_calendrier', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            sprintf('Dernier rappel — confirmez votre place pour %s', $creneau->getNom()),
            sprintf(
                "Bonjour %s,\n\nVotre place pour le créneau « %s » du %s expire bientôt. Confirmez votre venue sur Axiobad avant l'échéance, sans quoi la place sera proposée à la personne suivante : %s",
                $licencie->getPrenom(),
                $creneau->getNom(),
                $date->format('d/m/Y'),
                $url
            ),
            url: $url
        );
    }

    public function invitationBientotExpiree(Licencie $licencie): bool
    {
        return $this->envoyer(
            $licencie,
            'Votre invitation Axiobad expire bientôt',
            sprintf(
                "Bonjour %s,\n\nVotre lien d'activation de compte sur Axiobad expire dans moins de 48h. Pensez à l'utiliser, ou demandez au bureau de vous en renvoyer un nouveau.",
                $licencie->getPrenom()
            )
        );
    }

    public function adhesionImpayee(Licencie $licencie, Adhesion $adhesion): bool
    {
        $url = $this->urlGenerator->generate('app_mon_profil', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            'Rappel — adhésion en attente de paiement',
            sprintf(
                "Bonjour %s,\n\nVotre adhésion pour la saison %s est encore en attente de paiement. Retrouvez le détail sur Axiobad : %s",
                $licencie->getPrenom(),
                $adhesion->getSaison()?->getLibelle(),
                $url
            ),
            url: $url
        );
    }

    public function evenementAVenir(Licencie $licencie, Evenement $evenement): bool
    {
        $url = $this->urlGenerator->generate('app_evenement_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            sprintf('Rappel — %s approche', $evenement->getTitre()),
            sprintf(
                "Bonjour %s,\n\nL'évènement « %s » approche (%s). Retrouvez le détail sur Axiobad : %s",
                $licencie->getPrenom(),
                $evenement->getTitre(),
                $evenement->getDateDebut()?->format('d/m/Y'),
                $url
            ),
            url: $url
        );
    }

    public function convocationInterclub(Licencie $licencie, RencontreInterclub $rencontre): bool
    {
        $url = $this->urlGenerator->generate('app_calendrier', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->envoyer(
            $licencie,
            'Rappel — convocation interclubs à venir',
            sprintf(
                "Bonjour %s,\n\nVous êtes convoqué(e) pour la rencontre interclubs contre %s le %s. Merci d'indiquer votre présence sur Axiobad si ce n'est pas déjà fait : %s",
                $licencie->getPrenom(),
                $rencontre->getAdversaire(),
                $rencontre->getDateRencontre()?->format('d/m/Y'),
                $url
            ),
            url: $url
        );
    }

    /**
     * Envoi d'une communication libre (sujet/corps saisis par le bureau) à un destinataire.
     */
    public function communicationCiblee(Licencie $destinataire, string $sujet, string $corps): bool
    {
        // Communication manuelle et ciblée par le bureau : envoyée même si le licencié a
        // désactivé les notifications automatiques.
        return $this->envoyer($destinataire, $sujet, $corps, automatique: false);
    }

    /**
     * @param bool $automatique si true (par défaut), respecte la préférence de notification du
     *                          destinataire — utilisé pour tous les rappels/alertes automatiques,
     *                          jamais pour les communications ciblées envoyées manuellement
     */
    private function envoyer(?Licencie $destinataire, string $sujet, string $corps, bool $automatique = true, ?string $url = null): bool
    {
        if (!$destinataire || !$destinataire->getEmail()) {
            return false;
        }

        if ($automatique && !$destinataire->isNotificationsActivees()) {
            return false;
        }

        $envoye = $this->envoyerEmail($destinataire, $sujet, $corps);

        $extrait = $this->resumerPourPush($corps);

        // Best-effort, ne doit jamais faire échouer la notification email : un abonnement push
        // absent/expiré ou une clé VAPID non configurée est silencieusement ignoré.
        $pushEnvoye = $this->pushNotifier->notifier($destinataire, $sujet, $extrait, $url);

        $historique = (new NotificationEnvoyee())
            ->setDestinataire($destinataire)
            ->setSujet($sujet)
            ->setExtrait($extrait)
            ->setEmailEnvoye($envoye)
            ->setPushEnvoye($pushEnvoye);
        $this->entityManager->persist($historique);
        $this->entityManager->flush();

        return $envoye;
    }

    private function envoyerEmail(Licencie $destinataire, string $sujet, string $corps): bool
    {
        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($destinataire->getEmail())
            ->subject($sujet.' — Axiobad')
            ->text($corps);

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Échec d\'envoi de notification à {email} : {message}', [
                'email' => $destinataire->getEmail(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resumerPourPush(string $corps): string
    {
        $premiereLigne = trim(explode("\n", trim($corps))[2] ?? $corps);

        return mb_strlen($premiereLigne) > 150 ? mb_substr($premiereLigne, 0, 147).'...' : $premiereLigne;
    }
}
