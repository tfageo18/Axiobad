<?php

namespace App\Entity;

use App\Repository\ParametresClubRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglages globaux du club — une seule ligne en base (id=1), créée à la demande si absente
 * (voir ParametresClubRepository::obtenir()).
 */
#[ORM\Entity(repositoryClass: ParametresClubRepository::class)]
class ParametresClub
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nomClub = null;

    /**
     * Nom du club tel qu'il apparaît sur MyFFBaD (recherche club), utilisé pour la synchronisation
     * des fiches licenciés (numéro de licence, classements) — souvent identique à nomClub, mais pas
     * toujours (abréviations, orthographe officielle FFBaD différente).
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nomClubMyFfbad = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getNomClub(): ?string
    {
        return $this->nomClub;
    }

    public function setNomClub(?string $nomClub): static
    {
        $this->nomClub = $nomClub;

        return $this;
    }

    public function getNomClubMyFfbad(): ?string
    {
        return $this->nomClubMyFfbad;
    }

    public function setNomClubMyFfbad(?string $nomClubMyFfbad): static
    {
        $this->nomClubMyFfbad = $nomClubMyFfbad;

        return $this;
    }
}
