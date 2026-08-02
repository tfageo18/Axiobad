<?php

namespace App\Entity;

use App\Repository\ConvocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConvocationRepository::class)]
#[ORM\UniqueConstraint(columns: ['rencontre_id', 'licencie_id'])]
class Convocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RencontreInterclub::class, inversedBy: 'convocations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RencontreInterclub $rencontre = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Licencie $licencie = null;

    #[ORM\Column(nullable: true)]
    private ?bool $present = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $repondule = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRencontre(): ?RencontreInterclub
    {
        return $this->rencontre;
    }

    public function setRencontre(?RencontreInterclub $rencontre): static
    {
        $this->rencontre = $rencontre;

        return $this;
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

    public function isPresent(): ?bool
    {
        return $this->present;
    }

    public function setPresent(?bool $present): static
    {
        $this->present = $present;
        $this->repondule = new \DateTimeImmutable();

        return $this;
    }

    public function getRepondule(): ?\DateTimeImmutable
    {
        return $this->repondule;
    }
}
