<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contenu d'une séance dirigée : une par occurrence (créneau + date) d'un créneau encadré.
 * Saisi par un des entraîneurs du créneau (ou le bureau). Publiable aux licenciés inscrits à
 * cette occurrence — sinon reste visible des seuls entraîneurs/bureau.
 */
#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\UniqueConstraint(name: 'seance_unique', columns: ['creneau_id', 'date'])]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Creneau::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Creneau $creneau = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $redacteur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectifs = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column]
    private bool $publiee = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $modifieLe = null;

    /**
     * @var Collection<int, SeanceSchema>
     */
    #[ORM\OneToMany(targetEntity: SeanceSchema::class, mappedBy: 'seance', orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $schemas;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->schemas = new ArrayCollection();
    }

    /**
     * @return Collection<int, SeanceSchema>
     */
    public function getSchemas(): Collection
    {
        return $this->schemas;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): static
    {
        $this->creneau = $creneau;

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

    public function getRedacteur(): ?Licencie
    {
        return $this->redacteur;
    }

    public function setRedacteur(?Licencie $redacteur): static
    {
        $this->redacteur = $redacteur;

        return $this;
    }

    public function getObjectifs(): ?string
    {
        return $this->objectifs;
    }

    public function setObjectifs(?string $objectifs): static
    {
        $this->objectifs = $objectifs;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function isPubliee(): bool
    {
        return $this->publiee;
    }

    public function setPubliee(bool $publiee): static
    {
        $this->publiee = $publiee;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getModifieLe(): ?\DateTimeImmutable
    {
        return $this->modifieLe;
    }

    public function toucher(): static
    {
        $this->modifieLe = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Une séance "vide" (jamais renseignée) n'a pas d'intérêt à être affichée.
     */
    public function estVide(): bool
    {
        return !$this->objectifs && !$this->contenu && $this->schemas->isEmpty();
    }
}
