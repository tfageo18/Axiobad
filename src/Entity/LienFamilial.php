<?php

namespace App\Entity;

use App\Repository\LienFamilialRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien familial élargi entre deux licenciés (oncle/tante, grand-parent, frère/sœur,
 * beau-parent...), distinct du responsable légal : purement déclaratif, sans aucun accès aux
 * données sensibles ni action possible sur le compte de l'autre — sert uniquement à regrouper des
 * comptes dans "Ma famille" (visibilité des prochains créneaux et du statut d'adhésion).
 *
 * Nécessite le consentement explicite de la personne ciblée (ou de son/ses responsable(s) légal
 * (aux) si elle est mineure) : le lien reste EN_ATTENTE tant qu'il n'est pas accepté. Révocable à
 * tout moment par l'une ou l'autre partie.
 */
#[ORM\Entity(repositoryClass: LienFamilialRepository::class)]
class LienFamilial
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_ACCEPTE = 'ACCEPTE';

    public const TYPES = [
        'PARENT_ENFANT' => 'Parent / enfant',
        'FRERE_SOEUR' => 'Frère / sœur',
        'GRAND_PARENT_PETIT_ENFANT' => 'Grand-parent / petit-enfant',
        'ONCLE_TANTE_NEVEU_NIECE' => 'Oncle-tante / neveu-nièce',
        'COUSIN_COUSINE' => 'Cousin / cousine',
        'BEAU_PARENT_BEAU_ENFANT' => 'Beau-parent / beau-fils-belle-fille',
        'AUTRE' => 'Autre lien familial',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $demandeur = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $cible = null;

    #[ORM\Column(length: 40)]
    private ?string $typeLien = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private ?\DateTimeImmutable $demandeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $accepteLe = null;

    public function __construct()
    {
        $this->demandeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemandeur(): ?Licencie
    {
        return $this->demandeur;
    }

    public function setDemandeur(?Licencie $demandeur): static
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getCible(): ?Licencie
    {
        return $this->cible;
    }

    public function setCible(?Licencie $cible): static
    {
        $this->cible = $cible;

        return $this;
    }

    public function getTypeLien(): ?string
    {
        return $this->typeLien;
    }

    public function getTypeLienLabel(): string
    {
        return self::TYPES[$this->typeLien] ?? (string) $this->typeLien;
    }

    public function setTypeLien(?string $typeLien): static
    {
        $this->typeLien = $typeLien;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function estEnAttente(): bool
    {
        return self::STATUT_EN_ATTENTE === $this->statut;
    }

    public function estAccepte(): bool
    {
        return self::STATUT_ACCEPTE === $this->statut;
    }

    public function accepter(): static
    {
        $this->statut = self::STATUT_ACCEPTE;
        $this->accepteLe = new \DateTimeImmutable();

        return $this;
    }

    public function getDemandeLe(): ?\DateTimeImmutable
    {
        return $this->demandeLe;
    }

    public function getAccepteLe(): ?\DateTimeImmutable
    {
        return $this->accepteLe;
    }

    /**
     * Retourne l'autre licencié du lien vu depuis $depuis (utile pour l'affichage côté cible
     * comme côté demandeur avec la même vue).
     */
    public function getAutrePersonne(Licencie $depuis): ?Licencie
    {
        if ($this->demandeur === $depuis) {
            return $this->cible;
        }
        if ($this->cible === $depuis) {
            return $this->demandeur;
        }

        return null;
    }
}
