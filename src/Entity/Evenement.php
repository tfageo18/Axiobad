<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    public const TYPE_TOURNOI_INTERNE = 'TOURNOI_INTERNE';
    public const TYPE_BARBECUE = 'BARBECUE';
    public const TYPE_ASSEMBLEE_GENERALE = 'ASSEMBLEE_GENERALE';
    public const TYPE_STAGE = 'STAGE';
    public const TYPE_AUTRE = 'AUTRE';

    public const TYPES_LABELS = [
        self::TYPE_TOURNOI_INTERNE => 'Tournoi interne',
        self::TYPE_BARBECUE => 'Barbecue',
        self::TYPE_ASSEMBLEE_GENERALE => 'Assemblée générale',
        self::TYPE_STAGE => 'Stage',
        self::TYPE_AUTRE => 'Autre',
    ];

    public const VISIBILITE_TOUS = 'TOUS';
    public const VISIBILITE_BUREAU = 'BUREAU';

    public const VISIBILITES_LABELS = [
        self::VISIBILITE_TOUS => 'Tous les licenciés',
        self::VISIBILITE_BUREAU => 'Bureau uniquement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_AUTRE;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombrePlaces = null;

    /**
     * Qui peut voir/s'inscrire à cet événement — TOUS (défaut) ou BUREAU uniquement (ex.
     * réunions du bureau).
     */
    #[ORM\Column(length: 20)]
    private string $visibilite = self::VISIBILITE_TOUS;

    /**
     * @var Collection<int, Inscription>
     */
    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'evenement', orphanRemoval: true)]
    private Collection $inscriptions;

    /**
     * @var Collection<int, EvenementDocument>
     */
    #[ORM\OneToMany(targetEntity: EvenementDocument::class, mappedBy: 'evenement', orphanRemoval: true)]
    #[ORM\OrderBy(['ajouteLe' => 'DESC'])]
    private Collection $documents;

    public function __construct()
    {
        $this->inscriptions = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    /**
     * @return Collection<int, EvenementDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES_LABELS[$this->type] ?? $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getNombrePlaces(): ?int
    {
        return $this->nombrePlaces;
    }

    public function setNombrePlaces(?int $nombrePlaces): static
    {
        $this->nombrePlaces = $nombrePlaces;

        return $this;
    }

    public function getVisibilite(): string
    {
        return $this->visibilite;
    }

    public function getVisibiliteLabel(): string
    {
        return self::VISIBILITES_LABELS[$this->visibilite] ?? $this->visibilite;
    }

    public function setVisibilite(string $visibilite): static
    {
        $this->visibilite = in_array($visibilite, [self::VISIBILITE_TOUS, self::VISIBILITE_BUREAU], true)
            ? $visibilite
            : self::VISIBILITE_TOUS;

        return $this;
    }

    /**
     * L'événement est-il visible (et ouvert à l'inscription) pour ce licencié ?
     */
    public function estVisiblePar(?Licencie $licencie): bool
    {
        if (self::VISIBILITE_TOUS === $this->visibilite) {
            return true;
        }

        return null !== $licencie && in_array(Licencie::ROLE_BUREAU, $licencie->getRoles(), true);
    }

    /**
     * @return Collection<int, Inscription>
     */
    public function getInscriptions(): Collection
    {
        return $this->inscriptions;
    }

    /**
     * @return Collection<int, Inscription>
     */
    public function getParticipants(): Collection
    {
        return $this->inscriptions->filter(static fn (Inscription $i) => Inscription::STATUT_CONFIRMEE === $i->getStatut());
    }

    /**
     * @return Collection<int, Inscription>
     */
    public function getListeAttente(): Collection
    {
        return $this->inscriptions->filter(static fn (Inscription $i) => Inscription::STATUT_LISTE_ATTENTE === $i->getStatut());
    }

    public function aDesPlacesDisponibles(): bool
    {
        return null === $this->nombrePlaces || $this->getParticipants()->count() < $this->nombrePlaces;
    }

    public function getInscriptionDe(?Licencie $licencie): ?Inscription
    {
        if (!$licencie) {
            return null;
        }

        foreach ($this->inscriptions as $inscription) {
            if ($inscription->getLicencie() === $licencie) {
                return $inscription;
            }
        }

        return null;
    }
}
