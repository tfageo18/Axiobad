<?php

namespace App\Entity;

use App\Badminton\ClassementFfbad;
use App\Repository\CreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreneauRepository::class)]
class Creneau
{
    public const CATEGORIE_ADULTE = 'ADULTE';
    public const CATEGORIE_ENFANT = 'ENFANT';

    public const TYPE_LOISIR = 'LOISIR';
    public const TYPE_COMPETITEUR = 'COMPETITEUR';

    public const TYPES = [
        self::TYPE_LOISIR => 'Loisir',
        self::TYPE_COMPETITEUR => 'Compétiteur',
    ];

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

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(length: 10)]
    private string $categorie = self::CATEGORIE_ADULTE;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $classementMinimum = null;

    #[ORM\Column(length: 15)]
    private string $type = self::TYPE_LOISIR;

    #[ORM\Column]
    private bool $ouvertExternes = false;

    #[ORM\Column]
    private bool $ouvertAdos = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recurrenceDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recurrenceFin = null;

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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

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

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getClassementMinimum(): ?string
    {
        return $this->classementMinimum;
    }

    public function setClassementMinimum(?string $classementMinimum): static
    {
        $this->classementMinimum = $classementMinimum;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isOuvertExternes(): bool
    {
        return $this->ouvertExternes;
    }

    public function setOuvertExternes(bool $ouvertExternes): static
    {
        $this->ouvertExternes = $ouvertExternes;

        return $this;
    }

    public function isOuvertAdos(): bool
    {
        return $this->ouvertAdos;
    }

    public function setOuvertAdos(bool $ouvertAdos): static
    {
        $this->ouvertAdos = $ouvertAdos;

        return $this;
    }

    public function getRecurrenceDebut(): ?\DateTimeImmutable
    {
        return $this->recurrenceDebut;
    }

    public function setRecurrenceDebut(?\DateTimeImmutable $recurrenceDebut): static
    {
        $this->recurrenceDebut = $recurrenceDebut;

        return $this;
    }

    public function getRecurrenceFin(): ?\DateTimeImmutable
    {
        return $this->recurrenceFin;
    }

    public function setRecurrenceFin(?\DateTimeImmutable $recurrenceFin): static
    {
        $this->recurrenceFin = $recurrenceFin;

        return $this;
    }

    /**
     * Indique si ce créneau correspond au niveau et à la catégorie d'âge du licencié.
     */
    public function correspondA(Licencie $licencie): bool
    {
        if ($licencie->getCategorie() !== null && $licencie->getCategorie() !== $this->categorie) {
            return false;
        }

        if (null !== $this->classementMinimum) {
            if (ClassementFfbad::rang($licencie->getMeilleurClassement()) < ClassementFfbad::rang($this->classementMinimum)) {
                return false;
            }
        }

        return true;
    }
}
