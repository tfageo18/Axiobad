<?php

namespace App\Entity;

use App\Repository\MatchInterclubRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un match individuel (simple ou double) au sein d'une rencontre interclubs.
 */
#[ORM\Entity(repositoryClass: MatchInterclubRepository::class)]
class MatchInterclub
{
    public const TYPE_SH = 'SH';
    public const TYPE_SD = 'SD';
    public const TYPE_DH = 'DH';
    public const TYPE_DD = 'DD';
    public const TYPE_MX = 'MX';

    public const TYPES_LABELS = [
        self::TYPE_SH => 'Simple Homme',
        self::TYPE_SD => 'Simple Dame',
        self::TYPE_DH => 'Double Homme',
        self::TYPE_DD => 'Double Dame',
        self::TYPE_MX => 'Double Mixte',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RencontreInterclub::class, inversedBy: 'matchs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RencontreInterclub $rencontre = null;

    #[ORM\Column(length: 5)]
    private string $type = self::TYPE_SH;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $joueur1 = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $joueur2 = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adversaires = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $score = null;

    #[ORM\Column(nullable: true)]
    private ?bool $gagne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRencontre(): ?RencontreInterclub
    {
        return $this->rencontre;
    }

    public function setRencontre(?RencontreInterclub $rencontre): static
    {
        $this->rencontre = $rencontre;

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
        return self::TYPES_LABELS[$this->type] ?? $this->type;
    }

    public function estDouble(): bool
    {
        return in_array($this->type, [self::TYPE_DH, self::TYPE_DD, self::TYPE_MX], true);
    }

    public function getJoueur1(): ?Licencie
    {
        return $this->joueur1;
    }

    public function setJoueur1(?Licencie $joueur1): static
    {
        $this->joueur1 = $joueur1;

        return $this;
    }

    public function getJoueur2(): ?Licencie
    {
        return $this->joueur2;
    }

    public function setJoueur2(?Licencie $joueur2): static
    {
        $this->joueur2 = $joueur2;

        return $this;
    }

    /**
     * @return Licencie[]
     */
    public function getJoueurs(): array
    {
        return array_values(array_filter([$this->joueur1, $this->joueur2]));
    }

    public function getAdversaires(): ?string
    {
        return $this->adversaires;
    }

    public function setAdversaires(?string $adversaires): static
    {
        $this->adversaires = $adversaires;

        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getGagne(): ?bool
    {
        return $this->gagne;
    }

    public function setGagne(?bool $gagne): static
    {
        $this->gagne = $gagne;

        return $this;
    }
}
