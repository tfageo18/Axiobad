<?php

namespace App\Entity;

use App\Repository\NotificationEnvoyeeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique des notifications automatiques (et communications ciblées) envoyées, pour répondre
 * au besoin de traçabilité "qui a reçu quoi et quand". Ne stocke pas le corps intégral du
 * message, seulement le sujet et un court extrait — les communications elles-mêmes ont déjà leur
 * propre historique complet (voir CommunicationEnvoi).
 */
#[ORM\Entity(repositoryClass: NotificationEnvoyeeRepository::class)]
class NotificationEnvoyee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $destinataire = null;

    /**
     * Libellé du destinataire figé au moment de l'envoi (conservé même si le compte est
     * supprimé par la suite).
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $destinataireLibelle = null;

    #[ORM\Column(length: 255)]
    private ?string $sujet = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $extrait = null;

    #[ORM\Column]
    private bool $emailEnvoye = false;

    #[ORM\Column]
    private bool $pushEnvoye = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $envoyeLe = null;

    public function __construct()
    {
        $this->envoyeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): ?Licencie
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Licencie $destinataire): static
    {
        $this->destinataire = $destinataire;
        $this->destinataireLibelle = $destinataire?->getNomComplet();

        return $this;
    }

    public function getDestinataireLibelle(): ?string
    {
        return $this->destinataireLibelle ?? $this->destinataire?->getNomComplet();
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getExtrait(): ?string
    {
        return $this->extrait;
    }

    public function setExtrait(?string $extrait): static
    {
        $this->extrait = $extrait;

        return $this;
    }

    public function isEmailEnvoye(): bool
    {
        return $this->emailEnvoye;
    }

    public function setEmailEnvoye(bool $emailEnvoye): static
    {
        $this->emailEnvoye = $emailEnvoye;

        return $this;
    }

    public function isPushEnvoye(): bool
    {
        return $this->pushEnvoye;
    }

    public function setPushEnvoye(bool $pushEnvoye): static
    {
        $this->pushEnvoye = $pushEnvoye;

        return $this;
    }

    public function getEnvoyeLe(): ?\DateTimeImmutable
    {
        return $this->envoyeLe;
    }
}
