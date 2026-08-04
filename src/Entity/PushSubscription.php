<?php

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Abonnement push navigateur (Web Push) d'un licencié sur un appareil donné. Un même licencié
 * peut avoir plusieurs abonnements (un par navigateur/appareil sur lequel il a activé les
 * notifications push).
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Licencie $licencie = null;

    #[ORM\Column(type: 'text', unique: true)]
    private ?string $endpoint = null;

    #[ORM\Column(length: 255)]
    private ?string $p256dhKey = null;

    #[ORM\Column(length: 255)]
    private ?string $authToken = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creeLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getP256dhKey(): ?string
    {
        return $this->p256dhKey;
    }

    public function setP256dhKey(string $p256dhKey): static
    {
        $this->p256dhKey = $p256dhKey;

        return $this;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function setAuthToken(string $authToken): static
    {
        $this->authToken = $authToken;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }
}
