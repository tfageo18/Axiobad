<?php

namespace App\Entity;

use App\Repository\PresenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresenceRepository::class)]
#[ORM\UniqueConstraint(name: 'presence_unique', columns: ['creneau_id', 'licencie_id', 'date'])]
class Presence
{
    public const STATUT_CONFIRMEE = 'CONFIRMEE';
    public const STATUT_LISTE_ATTENTE = 'LISTE_ATTENTE';
    public const STATUT_EN_ATTENTE_CONFIRMATION = 'EN_ATTENTE_CONFIRMATION';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Creneau::class, inversedBy: 'presences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $licencie = null;

    /**
     * Date de l'occurrence concernée (le créneau se répète chaque semaine, la présence se déclare semaine par semaine).
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column]
    private bool $present = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $repondule = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $statutInscription = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $promotionExpiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): static
    {
        $this->creneau = $creneau;

        return $this;
    }

    public function getLicencie(): ?Licencie
    {
        return $this->licencie;
    }

    public function setLicencie(?Licencie $licencie): static
    {
        $this->licencie = $licencie;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isPresent(): bool
    {
        return $this->present;
    }

    public function setPresent(bool $present): static
    {
        $this->present = $present;
        $this->repondule = new \DateTimeImmutable();

        return $this;
    }

    public function getRepondule(): ?\DateTimeImmutable
    {
        return $this->repondule;
    }

    public function getStatutInscription(): ?string
    {
        return $this->statutInscription;
    }

    public function setStatutInscription(?string $statutInscription): static
    {
        $this->statutInscription = $statutInscription;

        return $this;
    }

    public function estConfirmee(): bool
    {
        return $this->present && self::STATUT_CONFIRMEE === $this->statutInscription;
    }

    public function estEnListeAttente(): bool
    {
        return $this->present && self::STATUT_LISTE_ATTENTE === $this->statutInscription;
    }

    public function estEnAttenteConfirmation(): bool
    {
        return $this->present && self::STATUT_EN_ATTENTE_CONFIRMATION === $this->statutInscription;
    }

    public function getPromotionExpiresAt(): ?\DateTimeImmutable
    {
        return $this->promotionExpiresAt;
    }

    public function setPromotionExpiresAt(?\DateTimeImmutable $promotionExpiresAt): static
    {
        $this->promotionExpiresAt = $promotionExpiresAt;

        return $this;
    }
}
