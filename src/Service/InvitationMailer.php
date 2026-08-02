<?php

namespace App\Service;

use App\Entity\Licencie;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $mailerFrom,
    ) {
    }

    public function envoyerInvitation(Licencie $licencie, string $token): void
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

        $this->mailer->send($email);
    }
}
