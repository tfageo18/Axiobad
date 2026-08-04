<?php

namespace App\Entity;

use App\Repository\DemandeCordageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeCordageRepository::class)]
class DemandeCordage
{
    public const STATUT_DEPOSEE = 'DEPOSEE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_PRETE = 'PRETE';
    public const STATUT_RECUPEREE = 'RECUPEREE';

    public const STATUTS_LABELS = [
        self::STATUT_DEPOSEE => 'Déposée',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_PRETE => 'Prête',
        self::STATUT_RECUPEREE => 'Récupérée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $licencie = null;

    #[ORM\ManyToOne(targetEntity: TypeCordage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TypeCordage $typeCordage = null;

    #[ORM\ManyToOne(targetEntity: Raquette::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Raquette $raquette = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tension = null;

    #[ORM\Column(length: 150)]
    private ?string $lieuDepose = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieuRetour = null;

    #[ORM\Column(precision: 6, scale: 2, nullable: true)]
    private ?string $prix = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_DEPOSEE;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Licencie $cordeur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDepot = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $datePrete = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRecuperee = null;

    public function __construct()
    {
        $this->dateDepot = new \DateTimeImmutable();
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

    public function getTypeCordage(): ?TypeCordage
    {
        return $this->typeCordage;
    }

    public function setTypeCordage(?TypeCordage $typeCordage): static
    {
        $this->typeCordage = $typeCordage;

        return $this;
    }

    public function getRaquette(): ?Raquette
    {
        return $this->raquette;
    }

    public function setRaquette(?Raquette $raquette): static
    {
        $this->raquette = $raquette;

        return $this;
    }

    public function getTension(): ?string
    {
        return $this->tension;
    }

    public function setTension(?string $tension): static
    {
        $this->tension = $tension;

        return $this;
    }

    public function getLieuDepose(): ?string
    {
        return $this->lieuDepose;
    }

    public function setLieuDepose(string $lieuDepose): static
    {
        $this->lieuDepose = $lieuDepose;

        return $this;
    }

    public function getLieuRetour(): ?string
    {
        return $this->lieuRetour;
    }

    public function setLieuRetour(?string $lieuRetour): static
    {
        $this->lieuRetour = $lieuRetour;

        return $this;
    }

    public function getPrix(): ?float
    {
        return null !== $this->prix ? (float) $this->prix : null;
    }

    public function setPrix(?float $prix): static
    {
        $this->prix = null !== $prix ? number_format($prix, 2, '.', '') : null;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getStatutLabel(): string
    {
        return self::STATUTS_LABELS[$this->statut] ?? $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCordeur(): ?Licencie
    {
        return $this->cordeur;
    }

    public function setCordeur(?Licencie $cordeur): static
    {
        $this->cordeur = $cordeur;

        return $this;
    }

    public function getDateDepot(): ?\DateTimeImmutable
    {
        return $this->dateDepot;
    }

    public function getDatePrete(): ?\DateTimeImmutable
    {
        return $this->datePrete;
    }

    public function setDatePrete(?\DateTimeImmutable $datePrete): static
    {
        $this->datePrete = $datePrete;

        return $this;
    }

    public function getDateRecuperee(): ?\DateTimeImmutable
    {
        return $this->dateRecuperee;
    }

    public function setDateRecuperee(?\DateTimeImmutable $dateRecuperee): static
    {
        $this->dateRecuperee = $dateRecuperee;

        return $this;
    }
}
