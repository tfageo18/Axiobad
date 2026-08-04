<?php

namespace App\Entity;

use App\Repository\RaquetteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaquetteRepository::class)]
class Raquette
{
    public const MARQUES = [
        'Yonex', 'Victor', 'Babolat', 'Li-Ning', 'Wilson', 'Apacs', 'Forza', 'Carlton', 'Dunlop', 'Head',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $licencie = null;

    #[ORM\Column(length: 100)]
    private ?string $marque = null;

    #[ORM\Column(length: 100)]
    private ?string $modele = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $signeDistinctif = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tensionHabituelle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLicencie(): ?Licencie
    {
        return $this->licencie;
    }

    public function setLicencie(?Licencie $licencie): static
    {
        $this->licencie = $licencie;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getSigneDistinctif(): ?string
    {
        return $this->signeDistinctif;
    }

    public function setSigneDistinctif(?string $signeDistinctif): static
    {
        $this->signeDistinctif = $signeDistinctif;

        return $this;
    }

    public function getTensionHabituelle(): ?string
    {
        return $this->tensionHabituelle;
    }

    public function setTensionHabituelle(?string $tensionHabituelle): static
    {
        $this->tensionHabituelle = $tensionHabituelle;

        return $this;
    }

    public function getLibelle(): string
    {
        return trim(sprintf('%s %s', $this->marque, $this->modele));
    }
}
