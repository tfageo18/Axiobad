<?php

namespace App\Entity;

use App\Repository\StockVolantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockVolantRepository::class)]
class StockVolant
{
    public const TYPE_PLUME = 'PLUME';
    public const TYPE_HYBRIDE = 'HYBRIDE';
    public const TYPE_PLASTIQUE = 'PLASTIQUE';

    public const TYPES = [
        self::TYPE_PLUME => 'Plume',
        self::TYPE_HYBRIDE => 'Hybride',
        self::TYPE_PLASTIQUE => 'Plastique',
    ];

    public const VITESSES = ['76', '77', '78', '79'];

    public const MARQUES = [
        'Yonex', 'Victor', 'Babolat', 'Li-Ning', 'Forza', 'RSL',
        'Ashaway', 'Carlton', 'Dunlop', 'Wilson', 'Apacs', 'Fleet',
    ];

    public const DESTINATION_COMPETITION = 'COMPETITION';
    public const DESTINATION_EQUIPE_1 = 'EQUIPE_1';
    public const DESTINATION_INTERCLUBS = 'INTERCLUBS';
    public const DESTINATION_LOISIR = 'LOISIR';
    public const DESTINATION_ENTRAINEMENT = 'ENTRAINEMENT';

    public const DESTINATIONS = [
        self::DESTINATION_COMPETITION => 'Compétition',
        self::DESTINATION_EQUIPE_1 => 'Équipe 1',
        self::DESTINATION_INTERCLUBS => 'Interclubs',
        self::DESTINATION_LOISIR => 'Loisir',
        self::DESTINATION_ENTRAINEMENT => 'Entraînement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 5)]
    private ?string $vitesse = null;

    #[ORM\Column(length: 20)]
    private ?string $destination = null;

    #[ORM\Column]
    private int $quantiteTubes = 0;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

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

    public function getVitesse(): ?string
    {
        return $this->vitesse;
    }

    public function setVitesse(string $vitesse): static
    {
        $this->vitesse = $vitesse;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDestinationLabel(): string
    {
        return self::DESTINATIONS[$this->destination] ?? $this->destination;
    }

    public function getQuantiteTubes(): int
    {
        return $this->quantiteTubes;
    }

    public function setQuantiteTubes(int $quantiteTubes): static
    {
        $this->quantiteTubes = $quantiteTubes;

        return $this;
    }

    /**
     * Applique un mouvement de stock (positif pour une entrée, négatif pour une sortie).
     */
    public function ajusterQuantite(int $delta): static
    {
        $this->quantiteTubes = max(0, $this->quantiteTubes + $delta);

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

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;

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
        return $this->quantiteTubes * ($this->getPrixUnitaire() ?? 0.0);
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
        return null !== $this->seuilAlerte && $this->quantiteTubes <= $this->seuilAlerte;
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
}
