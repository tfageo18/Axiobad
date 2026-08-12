<?php

namespace App\Entity;

use App\Repository\StockCordageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Article de stock de cordage : soit une bobine (quantité suivie en mètres, un cordage de
 * raquette consommant environ 10 m), soit des sachets individuels (quantité suivie en nombre de
 * sachets, un sachet = un cordage). Deux articles peuvent avoir la même marque/le même modèle
 * tout en étant stockés à des endroits différents : ce sont alors deux lignes distinctes.
 */
#[ORM\Entity(repositoryClass: StockCordageRepository::class)]
class StockCordage
{
    public const TYPE_BOBINE = 'BOBINE';
    public const TYPE_SACHET = 'SACHET';

    public const TYPES = [
        self::TYPE_BOBINE => 'Bobine',
        self::TYPE_SACHET => 'Sachet individuel',
    ];

    /**
     * Longueur consommée par défaut pour corder une raquette à partir d'une bobine.
     */
    public const METRES_PAR_RAQUETTE = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

    /**
     * Quantité en stock : mètres restants pour une bobine, nombre de sachets pour un sachet.
     */
    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(precision: 6, scale: 2, nullable: true)]
    private ?string $prixUnitaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $seuilAlerte = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieuStockage = null;

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

    public function isBobine(): bool
    {
        return self::TYPE_BOBINE === $this->type;
    }

    public function getUniteLabel(): string
    {
        return $this->isBobine() ? 'm' : 'sachet(s)';
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

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getPrixUnitaire(): ?float
    {
        return null !== $this->prixUnitaire ? (float) $this->prixUnitaire : null;
    }

    public function setPrixUnitaire(?float $prixUnitaire): static
    {
        $this->prixUnitaire = null !== $prixUnitaire ? number_format($prixUnitaire, 2, '.', '') : null;

        return $this;
    }

    public function getValeurStock(): float
    {
        return $this->quantite * ($this->getPrixUnitaire() ?? 0.0);
    }

    public function getSeuilAlerte(): ?int
    {
        return $this->seuilAlerte;
    }

    public function setSeuilAlerte(?int $seuilAlerte): static
    {
        $this->seuilAlerte = $seuilAlerte;

        return $this;
    }

    public function estSousLeSeuil(): bool
    {
        return null !== $this->seuilAlerte && $this->quantite <= $this->seuilAlerte;
    }

    public function getLieuStockage(): ?string
    {
        return $this->lieuStockage;
    }

    public function setLieuStockage(?string $lieuStockage): static
    {
        $this->lieuStockage = $lieuStockage;

        return $this;
    }

    public function getLibelle(): string
    {
        $libelle = trim(($this->marque ?? '').' '.($this->modele ?? ''));

        return '' !== $libelle ? $libelle : $this->getTypeLabel();
    }
}
