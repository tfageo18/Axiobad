<?php

namespace App\Entity;

use App\Repository\GymnaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GymnaseRepository::class)]
class Gymnase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    /**
     * @var Collection<int, Creneau>
     */
    #[ORM\OneToMany(targetEntity: Creneau::class, mappedBy: 'gymnase', orphanRemoval: true)]
    private Collection $creneaux;

    /**
     * @var Collection<int, Licencie>
     */
    #[ORM\ManyToMany(targetEntity: Licencie::class)]
    #[ORM\JoinTable(name: 'gymnase_porteur_cles')]
    private Collection $porteursCles;

    public function __construct()
    {
        $this->creneaux = new ArrayCollection();
        $this->porteursCles = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * @return Collection<int, Creneau>
     */
    public function getCreneaux(): Collection
    {
        return $this->creneaux;
    }

    /**
     * @return Collection<int, Licencie>
     */
    public function getPorteursCles(): Collection
    {
        return $this->porteursCles;
    }

    public function addPorteurCles(Licencie $licencie): static
    {
        if (!$this->porteursCles->contains($licencie)) {
            $this->porteursCles->add($licencie);
        }

        return $this;
    }

    public function removePorteurCles(Licencie $licencie): static
    {
        $this->porteursCles->removeElement($licencie);

        return $this;
    }
}
