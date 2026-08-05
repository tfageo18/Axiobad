<?php

namespace App\Entity;

use App\Repository\ModeleCommunicationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Modèle réutilisable (sujet + corps) pour la communication ciblée, afin d'éviter de retaper les
 * messages envoyés régulièrement (rappel de réunion, information de rentrée...).
 */
#[ORM\Entity(repositoryClass: ModeleCommunicationRepository::class)]
class ModeleCommunication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $sujet = null;

    #[ORM\Column(type: 'text')]
    private ?string $corps = null;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getCorps(): ?string
    {
        return $this->corps;
    }

    public function setCorps(string $corps): static
    {
        $this->corps = $corps;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }
}
