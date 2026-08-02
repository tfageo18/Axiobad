<?php

namespace App\Entity;

use App\Repository\RencontreInterclubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RencontreInterclubRepository::class)]
class RencontreInterclub
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Equipe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Equipe $equipe = null;

    #[ORM\Column]
    private ?int $journee = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateRencontre = null;

    #[ORM\Column(length: 150)]
    private ?string $lieu = null;

    #[ORM\Column(length: 150)]
    private ?string $adversaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreEquipe = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreAdversaire = null;

    /**
     * @var Collection<int, Convocation>
     */
    #[ORM\OneToMany(targetEntity: Convocation::class, mappedBy: 'rencontre', orphanRemoval: true)]
    private Collection $convocations;

    public function __construct()
    {
        $this->convocations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipe(): ?Equipe
    {
        return $this->equipe;
    }

    public function setEquipe(?Equipe $equipe): static
    {
        $this->equipe = $equipe;

        return $this;
    }

    public function getJournee(): ?int
    {
        return $this->journee;
    }

    public function setJournee(int $journee): static
    {
        $this->journee = $journee;

        return $this;
    }

    public function getDateRencontre(): ?\DateTimeImmutable
    {
        return $this->dateRencontre;
    }

    public function setDateRencontre(\DateTimeImmutable $dateRencontre): static
    {
        $this->dateRencontre = $dateRencontre;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getAdversaire(): ?string
    {
        return $this->adversaire;
    }

    public function setAdversaire(string $adversaire): static
    {
        $this->adversaire = $adversaire;

        return $this;
    }

    public function getScoreEquipe(): ?int
    {
        return $this->scoreEquipe;
    }

    public function setScoreEquipe(?int $scoreEquipe): static
    {
        $this->scoreEquipe = $scoreEquipe;

        return $this;
    }

    public function getScoreAdversaire(): ?int
    {
        return $this->scoreAdversaire;
    }

    public function setScoreAdversaire(?int $scoreAdversaire): static
    {
        $this->scoreAdversaire = $scoreAdversaire;

        return $this;
    }

    public function aUnScore(): bool
    {
        return null !== $this->scoreEquipe && null !== $this->scoreAdversaire;
    }

    /**
     * @return Collection<int, Convocation>
     */
    public function getConvocations(): Collection
    {
        return $this->convocations;
    }

    public function getConvocationDe(?Licencie $licencie): ?Convocation
    {
        if (!$licencie) {
            return null;
        }

        foreach ($this->convocations as $convocation) {
            if ($convocation->getLicencie() === $licencie) {
                return $convocation;
            }
        }

        return null;
    }
}
