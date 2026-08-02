<?php

namespace App\Entity;

use App\Repository\CreneauOuvertureRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Assignation, pour une occurrence précise (créneau + date), des licenciés qui ouvrent et ferment le gymnase.
 */
#[ORM\Entity(repositoryClass: CreneauOuvertureRepository::class)]
#[ORM\UniqueConstraint(name: 'creneau_ouverture_unique', columns: ['creneau_id', 'date'])]
class CreneauOuverture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Creneau::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Creneau $creneau = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Licencie $licencieOuverture = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Licencie $licencieFermeture = null;

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

    public function getLicencieOuverture(): ?Licencie
    {
        return $this->licencieOuverture;
    }

    public function setLicencieOuverture(?Licencie $licencieOuverture): static
    {
        $this->licencieOuverture = $licencieOuverture;

        return $this;
    }

    public function getLicencieFermeture(): ?Licencie
    {
        return $this->licencieFermeture;
    }

    public function setLicencieFermeture(?Licencie $licencieFermeture): static
    {
        $this->licencieFermeture = $licencieFermeture;

        return $this;
    }
}
