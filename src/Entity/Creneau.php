<?php

namespace App\Entity;

use App\Repository\CreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreneauRepository::class)]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Gymnase::class, inversedBy: 'creneaux')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gymnase $gymnase = null;

    #[ORM\Column(length: 10)]
    private ?string $jourSemaine = null;

    #[ORM\Column(type: 'time_immutable')]
    private ?\DateTimeImmutable $heureDebut = null;

    #[ORM\Column(type: 'time_immutable')]
    private ?\DateTimeImmutable $heureFin = null;

    #[ORM\Column]
    private bool $encadre = false;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Licencie $entraineur = null;

    /**
     * @var Collection<int, Presence>
     */
    #[ORM\OneToMany(targetEntity: Presence::class, mappedBy: 'creneau', orphanRemoval: true)]
    private Collection $presences;

    public function __construct()
    {
        $this->presences = new ArrayCollection();
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

    public function getGymnase(): ?Gymnase
    {
        return $this->gymnase;
    }

    public function setGymnase(?Gymnase $gymnase): static
    {
        $this->gymnase = $gymnase;

        return $this;
    }

    public function getJourSemaine(): ?string
    {
        return $this->jourSemaine;
    }

    public function setJourSemaine(string $jourSemaine): static
    {
        $this->jourSemaine = $jourSemaine;

        return $this;
    }

    public function getHeureDebut(): ?\DateTimeImmutable
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeImmutable $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): ?\DateTimeImmutable
    {
        return $this->heureFin;
    }

    public function setHeureFin(\DateTimeImmutable $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function isEncadre(): bool
    {
        return $this->encadre;
    }

    public function setEncadre(bool $encadre): static
    {
        $this->encadre = $encadre;

        if (!$encadre) {
            $this->entraineur = null;
        }

        return $this;
    }

    public function getEntraineur(): ?Licencie
    {
        return $this->entraineur;
    }

    public function setEntraineur(?Licencie $entraineur): static
    {
        $this->entraineur = $entraineur;

        return $this;
    }

    /**
     * @return Collection<int, Presence>
     */
    public function getPresences(): Collection
    {
        return $this->presences;
    }
}
