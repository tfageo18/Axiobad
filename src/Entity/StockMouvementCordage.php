<?php

namespace App\Entity;

use App\Repository\StockMouvementCordageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMouvementCordageRepository::class)]
class StockMouvementCordage
{
    public const TYPE_ENTREE = 'ENTREE';
    public const TYPE_SORTIE = 'SORTIE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StockCordage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockCordage $article = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $auteur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?StockCordage
    {
        return $this->article;
    }

    public function setArticle(?StockCordage $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isEntree(): bool
    {
        return self::TYPE_ENTREE === $this->type;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
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
}
