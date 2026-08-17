<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversation privée à deux entre deux licenciés (type messagerie). Le contenu n'est visible
 * que des deux participants — pas d'accès bureau, même pour la modération (à revoir plus tard si
 * un besoin de modération apparaît).
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $participant1 = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $participant2 = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dernierMessageLe = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $dernierMessageExpediteur = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $vuParParticipant1Le = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $vuParParticipant2Le = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant1(): ?Licencie
    {
        return $this->participant1;
    }

    public function setParticipant1(?Licencie $participant1): static
    {
        $this->participant1 = $participant1;

        return $this;
    }

    public function getParticipant2(): ?Licencie
    {
        return $this->participant2;
    }

    public function setParticipant2(?Licencie $participant2): static
    {
        $this->participant2 = $participant2;

        return $this;
    }

    public function estParticipant(?Licencie $licencie): bool
    {
        return null !== $licencie && ($this->participant1 === $licencie || $this->participant2 === $licencie);
    }

    public function getAutreParticipant(?Licencie $licencie): ?Licencie
    {
        if ($this->participant1 === $licencie) {
            return $this->participant2;
        }
        if ($this->participant2 === $licencie) {
            return $this->participant1;
        }

        return null;
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

    public function getVuLePar(?Licencie $licencie): ?\DateTimeImmutable
    {
        if ($this->participant1 === $licencie) {
            return $this->vuParParticipant1Le;
        }
        if ($this->participant2 === $licencie) {
            return $this->vuParParticipant2Le;
        }

        return null;
    }

    public function marquerVuPar(?Licencie $licencie, ?\DateTimeImmutable $date = null): static
    {
        $date ??= new \DateTimeImmutable();

        if ($this->participant1 === $licencie) {
            $this->vuParParticipant1Le = $date;
        } elseif ($this->participant2 === $licencie) {
            $this->vuParParticipant2Le = $date;
        }

        return $this;
    }

    /**
     * Y a-t-il un message non lu par ce participant (envoyé par l'autre, après sa dernière
     * visite) ?
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
