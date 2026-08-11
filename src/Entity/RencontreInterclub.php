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

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\ManyToOne(targetEntity: Gymnase::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Gymnase $gymnase = null;

    #[ORM\Column(length: 150)]
    private ?string $adversaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreEquipe = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreAdversaire = null;

    #[ORM\Column]
    private bool $domicile = true;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureRdv = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $capitaineRencontre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $covoiturage = null;

    /**
     * @var Collection<int, Convocation>
     */
    #[ORM\OneToMany(targetEntity: Convocation::class, mappedBy: 'rencontre', orphanRemoval: true)]
    private Collection $convocations;

    /**
     * @var Collection<int, MatchInterclub>
     */
    #[ORM\OneToMany(targetEntity: MatchInterclub::class, mappedBy: 'rencontre', orphanRemoval: true)]
    private Collection $matchs;

    public function __construct()
    {
        $this->convocations = new ArrayCollection();
        $this->matchs = new ArrayCollection();
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

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getGymnase(): ?Gymnase
    {
        return $this->gymnase;
    }

    public function setGymnase(?Gymnase $gymnase): static
    {
        $this->gymnase = $gymnase;

        return $this;
    }

    public function getLieuAffiche(): string
    {
        if ($this->domicile && null !== $this->gymnase) {
            return $this->gymnase->getNom();
        }

        return $this->lieu ?? '';
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

    public function isDomicile(): bool
    {
        return $this->domicile;
    }

    public function setDomicile(bool $domicile): static
    {
        $this->domicile = $domicile;

        return $this;
    }

    public function getHeureRdv(): ?\DateTimeImmutable
    {
        return $this->heureRdv;
    }

    public function setHeureRdv(?\DateTimeImmutable $heureRdv): static
    {
        $this->heureRdv = $heureRdv;

        return $this;
    }

    public function getCapitaineRencontre(): ?Licencie
    {
        return $this->capitaineRencontre;
    }

    public function setCapitaineRencontre(?Licencie $capitaineRencontre): static
    {
        $this->capitaineRencontre = $capitaineRencontre;

        return $this;
    }

    public function getCovoiturage(): ?string
    {
        return $this->covoiturage;
    }

    public function setCovoiturage(?string $covoiturage): static
    {
        $this->covoiturage = $covoiturage;

        return $this;
    }

    /**
     * @return Collection<int, Convocation>
     */
    public function getConvocations(): Collection
    {
        return $this->convocations;
    }

    /**
     * @return Collection<int, MatchInterclub>
     */
    public function getMatchs(): Collection
    {
        return $this->matchs;
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
