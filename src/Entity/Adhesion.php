<?php

namespace App\Entity;

use App\Repository\AdhesionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Statut d'adhésion (cotisation) d'un licencié pour une saison donnée.
 */
#[ORM\Entity(repositoryClass: AdhesionRepository::class)]
#[ORM\UniqueConstraint(name: 'adhesion_unique', columns: ['licencie_id', 'saison_id'])]
class Adhesion
{
    public const STATUT_PAYEE = 'PAYEE';
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EXONEREE = 'EXONEREE';

    public const STATUTS = [
        self::STATUT_PAYEE => 'Payée',
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_EXONEREE => 'Exonérée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $licencie = null;

    #[ORM\ManyToOne(targetEntity: Saison::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    #[ORM\Column(length: 15)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $montantTotal = null;

    /**
     * @var Collection<int, PaiementAdhesion>
     */
    #[ORM\OneToMany(targetEntity: PaiementAdhesion::class, mappedBy: 'adhesion', orphanRemoval: true)]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSaison(): ?Saison
    {
        return $this->saison;
    }

    public function setSaison(?Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /**
     * Conservé pour compatibilité avec l'affichage existant : "payée" = statut PAYEE.
     */
    public function isPayee(): bool
    {
        return self::STATUT_PAYEE === $this->statut;
    }

    public function getMontantTotal(): ?float
    {
        return null !== $this->montantTotal ? (float) $this->montantTotal : null;
    }

    public function setMontantTotal(?float $montantTotal): static
    {
        $this->montantTotal = null !== $montantTotal ? number_format($montantTotal, 2, '.', '') : null;

        return $this;
    }

    /**
     * @return Collection<int, PaiementAdhesion>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function getMontantPaye(): float
    {
        $total = 0.0;
        foreach ($this->paiements as $paiement) {
            $total += $paiement->getMontant();
        }

        return $total;
    }

    /**
     * Null si aucun montant total n'a été renseigné (rien à calculer).
     */
    public function getMontantRestant(): ?float
    {
        if (null === $this->montantTotal) {
            return null;
        }

        return round($this->getMontantTotal() - $this->getMontantPaye(), 2);
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        $dates = array_map(static fn (PaiementAdhesion $p) => $p->getDate(), $this->paiements->toArray());
        if (!$dates) {
            return null;
        }

        return max($dates);
    }
}
