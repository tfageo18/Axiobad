<?php

namespace App\Entity;

use App\Repository\LicencieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: LicencieRepository::class)]
#[ORM\Table(name: '`licencie`')]
class Licencie implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_LICENCIE = 'ROLE_LICENCIE';
    public const ROLE_BUREAU = 'ROLE_BUREAU';
    public const ROLE_ENTRAINEUR = 'ROLE_ENTRAINEUR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroLicence = null;

    #[ORM\Column(nullable: true)]
    private ?int $classementSimple = null;

    #[ORM\Column(nullable: true)]
    private ?int $classementDouble = null;

    #[ORM\Column(nullable: true)]
    private ?int $classementMixte = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $classementMisAJourLe = null;

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $activationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activationTokenExpiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_LICENCIE;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isMembreBureau(): bool
    {
        return in_array(self::ROLE_BUREAU, $this->roles, true);
    }

    public function isEntraineur(): bool
    {
        return in_array(self::ROLE_ENTRAINEUR, $this->roles, true);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim(sprintf('%s %s', $this->prenom, $this->nom));
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getNumeroLicence(): ?string
    {
        return $this->numeroLicence;
    }

    public function setNumeroLicence(?string $numeroLicence): static
    {
        $this->numeroLicence = $numeroLicence;

        return $this;
    }

    public function getClassementSimple(): ?int
    {
        return $this->classementSimple;
    }

    public function setClassementSimple(?int $classementSimple): static
    {
        $this->classementSimple = $classementSimple;

        return $this;
    }

    public function getClassementDouble(): ?int
    {
        return $this->classementDouble;
    }

    public function setClassementDouble(?int $classementDouble): static
    {
        $this->classementDouble = $classementDouble;

        return $this;
    }

    public function getClassementMixte(): ?int
    {
        return $this->classementMixte;
    }

    public function setClassementMixte(?int $classementMixte): static
    {
        $this->classementMixte = $classementMixte;

        return $this;
    }

    public function getClassementMisAJourLe(): ?\DateTimeImmutable
    {
        return $this->classementMisAJourLe;
    }

    public function setClassementMisAJourLe(?\DateTimeImmutable $classementMisAJourLe): static
    {
        $this->classementMisAJourLe = $classementMisAJourLe;

        return $this;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;

        return $this;
    }

    public function getActivationToken(): ?string
    {
        return $this->activationToken;
    }

    public function getActivationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->activationTokenExpiresAt;
    }

    public function isActivationTokenValid(): bool
    {
        return null !== $this->activationToken
            && null !== $this->activationTokenExpiresAt
            && $this->activationTokenExpiresAt > new \DateTimeImmutable();
    }

    public function generateActivationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->activationToken = $token;
        $this->activationTokenExpiresAt = new \DateTimeImmutable('+7 days');

        return $token;
    }

    public function clearActivationToken(): static
    {
        $this->activationToken = null;
        $this->activationTokenExpiresAt = null;

        return $this;
    }
}
