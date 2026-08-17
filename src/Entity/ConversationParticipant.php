<?php

namespace App\Entity;

use App\Repository\ConversationParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un participant d'une conversation (1 à N par conversation) — porte aussi la date de dernière
 * lecture propre à ce participant, pour le calcul des messages non lus.
 */
#[ORM\Entity(repositoryClass: ConversationParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'conversation_participant_unique', columns: ['conversation_id', 'licencie_id'])]
class ConversationParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $licencie = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $vuLe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

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

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }

    public function setVuLe(?\DateTimeImmutable $vuLe): static
    {
        $this->vuLe = $vuLe;

        return $this;
    }
}
