<?php

namespace App\Service;

use App\Entity\Licencie;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $mailerFrom,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Envoie l'email d'invitation. Retourne false (sans lever d'exception) si l'envoi échoue
     * (ex. adresse non joignable, quota d'envoi atteint) — l'erreur est journalisée, mais elle ne
     * doit jamais empêcher la création/gestion du compte du licencié.
     */
    public function envoyerInvitation(Licencie $licencie, string $token): bool
    {
        $lienActivation = $this->urlGenerator->generate('app_activation', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($licencie->getEmail())
            ->subject('Bienvenue sur Axiobad — activez votre compte')
            ->text(sprintf(
                "Bonjour %s,\n\nUn compte a été créé pour vous sur Axiobad, l'application de gestion du club.\n\nPour définir votre mot de passe et activer votre compte, cliquez sur le lien suivant :\n%s\n\nCe lien expire dans 7 jours.\n\nÀ bientôt sur les terrains !",
                $licencie->getPrenom(),
                $lienActivation
            ));

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Échec d\'envoi de l\'email d\'invitation à {email} : {message}', [
                'email' => $licencie->getEmail(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
