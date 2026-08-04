<?php

namespace App\Entity;

use App\Repository\CommunicationEnvoiRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique d'un envoi d'email ciblé (communication) par le bureau à un groupe de licenciés.
 */
#[ORM\Entity(repositoryClass: CommunicationEnvoiRepository::class)]
class CommunicationEnvoi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sujet = null;

    #[ORM\Column(type: 'text')]
    private ?string $corps = null;

    #[ORM\Column(length: 255)]
    private ?string $cibleLibelle = null;

    #[ORM\Column]
    private int $nombreDestinataires = 0;

    #[ORM\Column]
    private int $nombreEchecs = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emailsEnEchec = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Licencie $auteur = null;

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

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getCorps(): ?string
    {
        return $this->corps;
    }

    public function setCorps(string $corps): static
    {
        $this->corps = $corps;

        return $this;
    }

    public function getCibleLibelle(): ?string
    {
        return $this->cibleLibelle;
    }

    public function setCibleLibelle(string $cibleLibelle): static
    {
        $this->cibleLibelle = $cibleLibelle;

        return $this;
    }

    public function getNombreDestinataires(): int
    {
        return $this->nombreDestinataires;
    }

    public function setNombreDestinataires(int $nombreDestinataires): static
    {
        $this->nombreDestinataires = $nombreDestinataires;

        return $this;
    }

    public function getNombreEchecs(): int
    {
        return $this->nombreEchecs;
    }

    public function setNombreEchecs(int $nombreEchecs): static
    {
        $this->nombreEchecs = $nombreEchecs;

        return $this;
    }

    public function getEmailsEnEchec(): ?string
    {
        return $this->emailsEnEchec;
    }

    public function setEmailsEnEchec(?string $emailsEnEchec): static
    {
        $this->emailsEnEchec = $emailsEnEchec;

        return $this;
    }

    public function getAuteur(): ?Licencie
    {
        return $this->auteur;
    }

    public function setAuteur(?Licencie $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getEnvoyeLe(): ?\DateTimeImmutable
    {
        return $this->envoyeLe;
    }
}
