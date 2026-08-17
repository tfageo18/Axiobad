<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversation privée entre 2 licenciés (comme avant) ou davantage (discussion de groupe). Le
 * contenu n'est visible que des participants — pas d'accès bureau, même pour la modération (à
 * revoir plus tard si un besoin de modération apparaît).
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom du groupe (optionnel) — si absent, affiché comme la liste des autres participants.
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $titre = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $createur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dernierMessageLe = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $dernierMessageExpediteur = null;

    /**
     * @var Collection<int, ConversationParticipant>
     */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre ?: null;

        return $this;
    }

    public function getCreateur(): ?Licencie
    {
        return $this->createur;
    }

    public function setCreateur(?Licencie $createur): static
    {
        $this->createur = $createur;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getDernierMessageLe(): ?\DateTimeImmutable
    {
        return $this->dernierMessageLe;
    }

    public function getDernierMessageExpediteur(): ?Licencie
    {
        return $this->dernierMessageExpediteur;
    }

    public function enregistrerNouveauMessage(Licencie $expediteur, \DateTimeImmutable $date): static
    {
        $this->dernierMessageLe = $date;
        $this->dernierMessageExpediteur = $expediteur;

        // L'expéditeur a, par définition, vu son propre message.
        $this->marquerVuPar($expediteur, $date);

        return $this;
    }

    /**
     * @return Collection<int, ConversationParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function estGroupe(): bool
    {
        return $this->participants->count() > 2;
    }

    private function getParticipation(?Licencie $licencie): ?ConversationParticipant
    {
        foreach ($this->participants as $participation) {
            if ($participation->getLicencie() === $licencie) {
                return $participation;
            }
        }

        return null;
    }

    public function estParticipant(?Licencie $licencie): bool
    {
        return null !== $this->getParticipation($licencie);
    }

    public function ajouterParticipant(Licencie $licencie): static
    {
        if (!$this->estParticipant($licencie)) {
            $participation = (new ConversationParticipant())->setConversation($this)->setLicencie($licencie);
            $this->participants->add($participation);
        }

        return $this;
    }

    public function retirerParticipant(Licencie $licencie): static
    {
        $participation = $this->getParticipation($licencie);
        if ($participation) {
            $this->participants->removeElement($participation);
        }

        return $this;
    }

    /**
     * @return list<Licencie>
     */
    public function getAutresParticipants(?Licencie $moi): array
    {
        return array_values(array_filter(
            array_map(static fn (ConversationParticipant $p) => $p->getLicencie(), $this->participants->toArray()),
            static fn (?Licencie $l) => $l !== $moi
        ));
    }

    /**
     * Nom affiché de la conversation pour ce participant : le titre du groupe s'il y en a un,
     * sinon la liste des autres participants (ex. "Alice, Bob").
     */
    public function getNomAffiche(?Licencie $moi): string
    {
        if ($this->titre) {
            return $this->titre;
        }

        $autres = $this->getAutresParticipants($moi);
        if (!$autres) {
            return 'Conversation';
        }

        return implode(', ', array_map(static fn (Licencie $l) => $l->getNomComplet(), $autres));
    }

    public function getVuLePar(?Licencie $licencie): ?\DateTimeImmutable
    {
        return $this->getParticipation($licencie)?->getVuLe();
    }

    public function marquerVuPar(?Licencie $licencie, ?\DateTimeImmutable $date = null): static
    {
        $this->getParticipation($licencie)?->setVuLe($date ?? new \DateTimeImmutable());

        return $this;
    }

    /**
     * Y a-t-il un message non lu par ce participant (envoyé par quelqu'un d'autre, après sa
     * dernière visite) ?
     */
    public function aDesMessagesNonLusPour(?Licencie $licencie): bool
    {
        if (!$this->dernierMessageLe || $this->dernierMessageExpediteur === $licencie) {
            return false;
        }

        $vuLe = $this->getVuLePar($licencie);

        return null === $vuLe || $vuLe < $this->dernierMessageLe;
    }
}
