<?php

namespace App\Entity;

use App\Repository\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipeRepository::class)]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $championnat = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $division = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Licencie $capitaine = null;

    /**
     * @var Collection<int, Licencie>
     */
    #[ORM\ManyToMany(targetEntity: Licencie::class)]
    #[ORM\JoinTable(name: 'equipe_membre')]
    private Collection $membres;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getChampionnat(): ?string
    {
        return $this->championnat;
    }

    public function setChampionnat(?string $championnat): static
    {
        $this->championnat = $championnat;

        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(?string $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCapitaine(): ?Licencie
    {
        return $this->capitaine;
    }

    public function setCapitaine(?Licencie $capitaine): static
    {
        $this->capitaine = $capitaine;

        return $this;
    }

    public function estCapitaine(?Licencie $licencie): bool
    {
        return null !== $licencie && $this->capitaine === $licencie;
    }

    /**
     * @return Collection<int, Licencie>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Licencie $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
        }

        return $this;
    }

    public function removeMembre(Licencie $membre): static
    {
        $this->membres->removeElement($membre);

        return $this;
    }
}
