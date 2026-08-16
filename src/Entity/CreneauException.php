<?php

namespace App\Entity;

use App\Repository\CreneauExceptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exception ponctuelle sur une occurrence précise (créneau + date) d'un créneau récurrent :
 * annulation, ou modification exceptionnelle (horaire, gymnase, capacité, entraîneur, note) sans
 * toucher au créneau récurrent lui-même ni à ses autres occurrences.
 */
#[ORM\Entity(repositoryClass: CreneauExceptionRepository::class)]
#[ORM\UniqueConstraint(name: 'creneau_exception_unique', columns: ['creneau_id', 'date'])]
class CreneauException
{
    public const TYPE_ANNULEE = 'ANNULEE';
    public const TYPE_MODIFIEE = 'MODIFIEE';

    public const TYPES_LABELS = [
        self::TYPE_ANNULEE => 'Annulée',
        self::TYPE_MODIFIEE => 'Modifiée exceptionnellement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Creneau::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Creneau $creneau = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_ANNULEE;

    #[ORM\ManyToOne(targetEntity: Gymnase::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Gymnase $gymnase = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureDebut = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteMax = null;

    /**
     * Remplace ponctuellement, pour cette seule date, l'entraîneur habituel du créneau (ex.
     * remplacement) — laisser vide pour garder les entraîneurs habituels du créneau.
     */
    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $entraineur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function estAnnulee(): bool
    {
        return self::TYPE_ANNULEE === $this->type;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES_LABELS[$this->type] ?? $this->type;
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

    public function getHeureDebut(): ?\DateTimeImmutable
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(?\DateTimeImmutable $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): ?\DateTimeImmutable
    {
        return $this->heureFin;
    }

    public function setHeureFin(?\DateTimeImmutable $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(?int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Valeurs effectives de l'occurrence : celles de l'exception si renseignées (type MODIFIEE),
     * sinon celles du créneau récurrent.
     */
    public function getGymnaseEffectif(): ?Gymnase
    {
        return $this->gymnase ?? $this->creneau?->getGymnase();
    }

    public function getHeureDebutEffective(): ?\DateTimeImmutable
    {
        return $this->heureDebut ?? $this->creneau?->getHeureDebut();
    }

    public function getHeureFinEffective(): ?\DateTimeImmutable
    {
        return $this->heureFin ?? $this->creneau?->getHeureFin();
    }

    public function getCapaciteMaxEffective(): ?int
    {
        return $this->estAnnulee() ? null : ($this->capaciteMax ?? $this->creneau?->getCapaciteMax());
    }

    /**
     * Entraîneur(s) effectifs de cette occurrence : celui de l'exception s'il remplace
     * ponctuellement les autres, sinon la liste habituelle des entraîneurs du créneau.
     *
     * @return list<Licencie>
     */
    public function getEntraineursEffectifs(): array
    {
        if ($this->entraineur) {
            return [$this->entraineur];
        }

        return $this->creneau?->getEntraineurs()->toArray() ?? [];
    }
}
