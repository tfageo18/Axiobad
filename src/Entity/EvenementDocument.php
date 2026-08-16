<?php

namespace App\Entity;

use App\Repository\EvenementDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Document attaché à un événement (ex. convocation, ordre du jour et PV d'une assemblée
 * générale) — stocké sur disque (var/uploads/evenements), téléchargeable par tout licencié
 * connecté, ajouté/supprimé par le bureau uniquement.
 */
#[ORM\Entity(repositoryClass: EvenementDocumentRepository::class)]
class EvenementDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 255)]
    private ?string $nomOriginal = null;

    /**
     * Chemin absolu du fichier sur le disque du serveur (jamais exposé tel quel côté client —
     * le téléchargement passe par une route dédiée qui vérifie les droits d'accès).
     */
    #[ORM\Column(length: 500)]
    private ?string $chemin = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $ajoutePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $ajouteLe = null;

    public function __construct()
    {
        $this->ajouteLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;

        return $this;
    }

    public function getNomOriginal(): ?string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(string $nomOriginal): static
    {
        $this->nomOriginal = $nomOriginal;

        return $this;
    }

    public function getChemin(): ?string
    {
        return $this->chemin;
    }

    public function setChemin(string $chemin): static
    {
        $this->chemin = $chemin;

        return $this;
    }

    public function getAjoutePar(): ?Licencie
    {
        return $this->ajoutePar;
    }

    public function setAjoutePar(?Licencie $ajoutePar): static
    {
        $this->ajoutePar = $ajoutePar;

        return $this;
    }

    public function getAjouteLe(): ?\DateTimeImmutable
    {
        return $this->ajouteLe;
    }
}
