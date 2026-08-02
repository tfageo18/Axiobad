<?php

namespace App\Entity;

use App\Repository\AdhesionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Statut d'adhésion (paiement de la cotisation) d'un licencié pour une saison donnée.
 */
#[ORM\Entity(repositoryClass: AdhesionRepository::class)]
#[ORM\UniqueConstraint(name: 'adhesion_unique', columns: ['licencie_id', 'saison_id'])]
class Adhesion
{
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

    #[ORM\Column]
    private bool $payee = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

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

    public function isPayee(): bool
    {
        return $this->payee;
    }

    public function setPayee(bool $payee): static
    {
        $this->payee = $payee;
        $this->datePaiement = $payee ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }
}
