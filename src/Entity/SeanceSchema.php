<?php

namespace App\Entity;

use App\Repository\SeanceSchemaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un schéma tactique (tableau blanc "terrain de badminton") attaché à une séance. Une séance
 * peut en contenir plusieurs (un par exercice). Stocké en JSON vectoriel (voir
 * public/js/tableau-blanc.js) — pas une image figée, donc réouvrable et modifiable.
 */
#[ORM\Entity(repositoryClass: SeanceSchemaRepository::class)]
class SeanceSchema
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Seance::class, inversedBy: 'schemas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Seance $seance = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    private string $donnees = '{"terrains":1,"formes":[]}';

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $modifieLe = null;

    public function __construct()
    {
        $this->modifieLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeance(): ?Seance
    {
        return $this->seance;
    }

    public function setSeance(?Seance $seance): static
    {
        $this->seance = $seance;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDonnees(): string
    {
        return $this->donnees;
    }

    public function setDonnees(string $donnees): static
    {
        $this->donnees = $donnees;
        $this->modifieLe = new \DateTimeImmutable();

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getModifieLe(): ?\DateTimeImmutable
    {
        return $this->modifieLe;
    }
}
