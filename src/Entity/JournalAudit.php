<?php

namespace App\Entity;

use App\Repository\JournalAuditRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace les actions sensibles effectuées dans l'application (paiements, données de santé,
 * responsables légaux, suppression de compte, rôles, stock, créneaux, adhésions...), pour
 * répondre au besoin de traçabilité avec des rôles multiples et des données sensibles.
 */
#[ORM\Entity(repositoryClass: JournalAuditRepository::class)]
class JournalAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $utilisateur = null;

    /**
     * Libellé de l'utilisateur figé au moment de l'action (conservé même si le compte est
     * supprimé par la suite).
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $utilisateurLibelle = null;

    #[ORM\Column(length: 60)]
    private ?string $action = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $objetType = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $objetLibelle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ancienneValeur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nouvelleValeur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $horodatage = null;

    public function __construct()
    {
        $this->horodatage = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Licencie
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Licencie $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        $this->utilisateurLibelle = $utilisateur?->getNomComplet();

        return $this;
    }

    public function getUtilisateurLibelle(): ?string
    {
        return $this->utilisateurLibelle ?? $this->utilisateur?->getNomComplet();
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getObjetType(): ?string
    {
        return $this->objetType;
    }

    public function setObjetType(?string $objetType): static
    {
        $this->objetType = $objetType;

        return $this;
    }

    public function getObjetLibelle(): ?string
    {
        return $this->objetLibelle;
    }

    public function setObjetLibelle(?string $objetLibelle): static
    {
        $this->objetLibelle = $objetLibelle;

        return $this;
    }

    public function getAncienneValeur(): ?string
    {
        return $this->ancienneValeur;
    }

    public function setAncienneValeur(?string $ancienneValeur): static
    {
        $this->ancienneValeur = $ancienneValeur;

        return $this;
    }

    public function getNouvelleValeur(): ?string
    {
        return $this->nouvelleValeur;
    }

    public function setNouvelleValeur(?string $nouvelleValeur): static
    {
        $this->nouvelleValeur = $nouvelleValeur;

        return $this;
    }

    public function getHorodatage(): ?\DateTimeImmutable
    {
        return $this->horodatage;
    }
}
