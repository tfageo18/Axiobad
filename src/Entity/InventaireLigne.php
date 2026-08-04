<?php

namespace App\Entity;

use App\Repository\InventaireLigneRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne d'inventaire porte sur un article (vêtement ou tube de volants — un seul des deux
 * est renseigné). Snapshot de la quantité théorique à la création de la campagne, puis quantité
 * comptée saisie par le bureau lors de l'inventaire physique.
 */
#[ORM\Entity(repositoryClass: InventaireLigneRepository::class)]
class InventaireLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventaireCampagne::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InventaireCampagne $campagne = null;

    #[ORM\ManyToOne(targetEntity: StockVetement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StockVetement $vetement = null;

    #[ORM\ManyToOne(targetEntity: StockVolant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StockVolant $volant = null;

    /**
     * Libellé figé de l'article au moment de la campagne (conservé même si l'article est
     * supprimé par la suite).
     */
    #[ORM\Column(length: 255)]
    private ?string $libelleArticle = null;

    #[ORM\Column]
    private int $quantiteTheorique = 0;

    #[ORM\Column(nullable: true)]
    private ?int $quantiteComptee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampagne(): ?InventaireCampagne
    {
        return $this->campagne;
    }

    public function setCampagne(?InventaireCampagne $campagne): static
    {
        $this->campagne = $campagne;

        return $this;
    }

    public function getVetement(): ?StockVetement
    {
        return $this->vetement;
    }

    public function setVetement(?StockVetement $vetement): static
    {
        $this->vetement = $vetement;

        return $this;
    }

    public function getVolant(): ?StockVolant
    {
        return $this->volant;
    }

    public function setVolant(?StockVolant $volant): static
    {
        $this->volant = $volant;

        return $this;
    }

    public function getLibelleArticle(): ?string
    {
        return $this->libelleArticle;
    }

    public function setLibelleArticle(string $libelleArticle): static
    {
        $this->libelleArticle = $libelleArticle;

        return $this;
    }

    public function getQuantiteTheorique(): int
    {
        return $this->quantiteTheorique;
    }

    public function setQuantiteTheorique(int $quantiteTheorique): static
    {
        $this->quantiteTheorique = $quantiteTheorique;

        return $this;
    }

    public function getQuantiteComptee(): ?int
    {
        return $this->quantiteComptee;
    }

    public function setQuantiteComptee(?int $quantiteComptee): static
    {
        $this->quantiteComptee = $quantiteComptee;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getEcart(): ?int
    {
        return null !== $this->quantiteComptee ? $this->quantiteComptee - $this->quantiteTheorique : null;
    }

    public function aUnEcart(): bool
    {
        return null !== $this->getEcart() && 0 !== $this->getEcart();
    }
}
