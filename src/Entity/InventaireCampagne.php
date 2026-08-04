<?php

namespace App\Entity;

use App\Repository\InventaireCampagneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Campagne d'inventaire physique du stock : à la création, une ligne est créée pour chaque
 * article avec sa quantité théorique (snapshot). Le bureau saisit ensuite la quantité comptée
 * pour chaque ligne, puis valide la campagne — ce qui régularise automatiquement le stock via des
 * mouvements d'entrée/sortie pour chaque écart constaté.
 */
#[ORM\Entity(repositoryClass: InventaireCampagneRepository::class)]
class InventaireCampagne
{
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_VALIDEE = 'VALIDEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $auteur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valideeLe = null;

    /**
     * @var Collection<int, InventaireLigne>
     */
    #[ORM\OneToMany(targetEntity: InventaireLigne::class, mappedBy: 'campagne', orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function estValidee(): bool
    {
        return self::STATUT_VALIDEE === $this->statut;
    }

    public function getAuteur(): ?Licencie
    {
        return $this->auteur;
    }

    public function setAuteur(?Licencie $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getValideeLe(): ?\DateTimeImmutable
    {
        return $this->valideeLe;
    }

    public function setValideeLe(?\DateTimeImmutable $valideeLe): static
    {
        $this->valideeLe = $valideeLe;

        return $this;
    }

    /**
     * @return Collection<int, InventaireLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function getNombreEcarts(): int
    {
        return count(array_filter($this->lignes->toArray(), static fn (InventaireLigne $l) => $l->aUnEcart()));
    }
}
