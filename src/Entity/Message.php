<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un message d'une conversation privée entre deux licenciés.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $expediteur = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $envoyeLe = null;

    public function __construct()
    {
        $this->envoyeLe = new \DateTimeImmutable();
    }

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

    public function getExpediteur(): ?Licencie
    {
        return $this->expediteur;
    }

    public function setExpediteur(?Licencie $expediteur): static
    {
        $this->expediteur = $expediteur;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getEnvoyeLe(): ?\DateTimeImmutable
    {
        return $this->envoyeLe;
    }
}
