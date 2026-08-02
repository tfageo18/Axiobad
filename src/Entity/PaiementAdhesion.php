<?php

namespace App\Entity;

use App\Repository\PaiementAdhesionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un versement (paiement) rattaché à une adhésion — une adhésion peut être payée en plusieurs fois.
 */
#[ORM\Entity(repositoryClass: PaiementAdhesionRepository::class)]
class PaiementAdhesion
{
    public const MOYEN_CB = 'CB';
    public const MOYEN_CHEQUE = 'CHEQUE';
    public const MOYEN_ESPECES = 'ESPECES';
    public const MOYEN_VIREMENT = 'VIREMENT';

    public const MOYENS = [
        self::MOYEN_CB => 'Carte bancaire',
        self::MOYEN_CHEQUE => 'Chèque',
        self::MOYEN_ESPECES => 'Espèces',
        self::MOYEN_VIREMENT => 'Virement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Adhesion::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adhesion $adhesion = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $montant = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    private string $moyen = self::MOYEN_ESPECES;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $numeroCheque = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdhesion(): ?Adhesion
    {
        return $this->adhesion;
    }

    public function setAdhesion(?Adhesion $adhesion): static
    {
        $this->adhesion = $adhesion;

        return $this;
    }

    public function getMontant(): float
    {
        return (float) $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = number_format($montant, 2, '.', '');

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

    public function getMoyen(): string
    {
        return $this->moyen;
    }

    public function setMoyen(string $moyen): static
    {
        $this->moyen = $moyen;

        return $this;
    }

    public function getMoyenLabel(): string
    {
        return self::MOYENS[$this->moyen] ?? $this->moyen;
    }

    public function getNumeroCheque(): ?string
    {
        return $this->numeroCheque;
    }

    public function setNumeroCheque(?string $numeroCheque): static
    {
        $this->numeroCheque = $numeroCheque;

        return $this;
    }
}
