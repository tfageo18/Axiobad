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

    /**
     * Administrateur du groupe : peut ajouter/retirer des participants et transmettre le rôle
     * d'admin. Le créateur d'une conversation en est admin par défaut.
     */
    #[ORM\Column]
    private bool $estAdmin = false;

    /**
     * Si renseignée, ce participant ne voit l'historique qu'à partir de cette date (ajout à un
     * groupe "sans l'historique"). Null = voit tout l'historique de la conversation.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $voitHistoriqueDepuis = null;

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

    public function isEstAdmin(): bool
    {
        return $this->estAdmin;
    }

    public function setEstAdmin(bool $estAdmin): static
    {
        $this->estAdmin = $estAdmin;

        return $this;
    }

    public function getVoitHistoriqueDepuis(): ?\DateTimeImmutable
    {
        return $this->voitHistoriqueDepuis;
    }

    public function setVoitHistoriqueDepuis(?\DateTimeImmutable $voitHistoriqueDepuis): static
    {
        $this->voitHistoriqueDepuis = $voitHistoriqueDepuis;

        return $this;
    }
}
