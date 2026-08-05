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
    public const STATUT_ENVOYE = 'ENVOYE';
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_ANNULE = 'ANNULE';

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

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_ENVOYE;

    /**
     * Date/heure à laquelle l'envoi doit avoir lieu, pour une communication programmée. Null pour
     * un envoi immédiat.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $planifiePour = null;

    /**
     * Identifiants des licenciés destinataires, figés au moment de la création (immédiate ou
     * programmée) — pour un envoi différé, la cible réelle au moment de l'envoi est celle-ci,
     * pas une nouvelle résolution qui pourrait avoir changé entre-temps.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $destinatairesIds = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pieceJointeNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pieceJointeChemin = null;

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

    public function setEnvoyeLe(\DateTimeImmutable $envoyeLe): static
    {
        $this->envoyeLe = $envoyeLe;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function estEnAttente(): bool
    {
        return self::STATUT_EN_ATTENTE === $this->statut;
    }

    public function getPlanifiePour(): ?\DateTimeImmutable
    {
        return $this->planifiePour;
    }

    public function setPlanifiePour(?\DateTimeImmutable $planifiePour): static
    {
        $this->planifiePour = $planifiePour;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getDestinatairesIds(): array
    {
        return $this->destinatairesIds ? json_decode($this->destinatairesIds, true) : [];
    }

    /**
     * @param int[] $ids
     */
    public function setDestinatairesIds(array $ids): static
    {
        $this->destinatairesIds = json_encode($ids);

        return $this;
    }

    public function getPieceJointeNom(): ?string
    {
        return $this->pieceJointeNom;
    }

    public function setPieceJointeNom(?string $pieceJointeNom): static
    {
        $this->pieceJointeNom = $pieceJointeNom;

        return $this;
    }

    public function getPieceJointeChemin(): ?string
    {
        return $this->pieceJointeChemin;
    }

    public function setPieceJointeChemin(?string $pieceJointeChemin): static
    {
        $this->pieceJointeChemin = $pieceJointeChemin;

        return $this;
    }
}
