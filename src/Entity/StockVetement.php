<?php

namespace App\Entity;

use App\Repository\StockVetementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockVetementRepository::class)]
class StockVetement
{
    public const TYPE_TSHIRT = 'TSHIRT';
    public const TYPE_SWEAT_SHIRT = 'SWEAT_SHIRT';
    public const TYPE_SURVETEMENT = 'SURVETEMENT';
    public const TYPE_PANTALON = 'PANTALON';
    public const TYPE_SHORT = 'SHORT';
    public const TYPE_CHAUSSETTES = 'CHAUSSETTES';

    public const TYPES = [
        self::TYPE_TSHIRT => 'T-shirt',
        self::TYPE_SWEAT_SHIRT => 'Sweat-shirt',
        self::TYPE_SURVETEMENT => 'Survêtement',
        self::TYPE_PANTALON => 'Pantalon',
        self::TYPE_SHORT => 'Short',
        self::TYPE_CHAUSSETTES => 'Chaussettes',
    ];

    public const TAILLES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    public const MARQUES = [
        'Nike', 'Adidas', 'Puma', 'Under Armour', 'Asics', 'New Balance',
        'Kappa', 'Decathlon / Kipsta', 'Errea', 'Hummel', 'Uhlsport', 'Mizuno',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 10)]
    private ?string $taille = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(string $taille): static
    {
        $this->taille = $taille;

        return $this;
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

    /**
     * Applique un mouvement de stock (positif pour une entrée, négatif pour une sortie).
     */
    public function ajusterQuantite(int $delta): static
    {
        $this->quantite = max(0, $this->quantite + $delta);

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}
