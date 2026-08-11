<?php

namespace App\Entity;

use App\Badminton\ClassementFfbad;
use App\Repository\LicencieRepository;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: LicencieRepository::class)]
#[ORM\Table(name: '`licencie`')]
class Licencie implements UserInterface, PasswordAuthenticatedUserInterface, EmailTwoFactorInterface, TotpTwoFactorInterface
{
    public const ROLE_LICENCIE = 'ROLE_LICENCIE';
    public const ROLE_BUREAU = 'ROLE_BUREAU';
    public const ROLE_ENTRAINEUR = 'ROLE_ENTRAINEUR';
    public const ROLE_CORDEUR = 'ROLE_CORDEUR';
    public const ROLE_STOCK = 'ROLE_STOCK';

    public const EMAIL_ADMIN_DEFAUT = 'admin@axiobad.local';

    public const GENRE_HOMME = 'HOMME';
    public const GENRE_FEMME = 'FEMME';

    public const GENRES = [
        self::GENRE_HOMME => 'Homme',
        self::GENRE_FEMME => 'Femme',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
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

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroLicence = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $classementSimple = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $classementDouble = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $classementMixte = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $classementMisAJourLe = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column]
    private bool $actif = true;

    /**
     * Date de désactivation du compte — sert de point de départ à la durée de conservation avant
     * anonymisation automatique (RGPD).
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $desactiveLe = null;

    /**
     * Un compte anonymisé (RGPD art. 17, purge après durée de conservation) garde son
     * identifiant et ses données comptables liées (adhésions, paiements), mais son identité et
     * ses données personnelles ont été effacées.
     */
    #[ORM\Column]
    private bool $anonymise = false;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $activationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activationTokenExpiresAt = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $responsableLegal1 = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $responsableLegal2 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $personnesAutoriseesRecuperation = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $contactUrgenceNom = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $contactUrgenceTelephone = null;

    #[ORM\Column]
    private bool $autorisationSortieSeul = false;

    #[ORM\Column]
    private bool $droitImage = false;

    /**
     * Allergies ou informations de santé utiles. Donnée sensible : visible uniquement du bureau,
     * des entraîneurs et des responsables légaux du licencié — jamais des autres licenciés.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $informationsSante = null;

    /**
     * Consentement explicite (RGPD, art. 9) au traitement des informations de santé, donné par le
     * licencié lui-même ou son responsable légal. Requis pour renseigner informationsSante.
     */
    #[ORM\Column]
    private bool $consentementDonneesSante = false;

    /**
     * Préférence de réception des notifications automatiques par email (rappels de créneau,
     * cordage prêt, adhésion impayée...). N'affecte pas les emails d'invitation/activation de
     * compte, ni les communications ciblées envoyées manuellement par le bureau.
     */
    #[ORM\Column]
    private bool $notificationsActivees = false;

    /**
     * Date à laquelle le licencié (ou son responsable légal) a demandé la suppression de son
     * compte (droit à l'effacement, RGPD art. 17). Null tant qu'aucune demande n'est en cours.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $suppressionDemandeeLe = null;

    /**
     * Équipe interclub par défaut du licencié, utilisée pour pré-filtrer la liste des rencontres.
     * Modifiable par le licencié lui-même ou par le bureau.
     */
    #[ORM\ManyToOne(targetEntity: Equipe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Equipe $equipePreferee = null;

    /**
     * Double authentification (MFA), optionnelle et au choix du licencié : par email ou par
     * application TOTP (Google Authenticator, etc.), les deux pouvant être activées en parallèle.
     */
    #[ORM\Column]
    private bool $emailAuthEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?string $emailAuthCode = null;

    #[ORM\Column]
    private bool $totpAuthEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?string $totpSecret = null;

    /**
     * Vérification périodique (à chaque connexion, au plus une fois tous les 7 jours) du mot de
     * passe auprès d'une base de fuites de données connues — rattrape les mots de passe acceptés
     * sans avoir pu être vérifiés (service externe injoignable) et détecte ceux qui deviennent
     * compromis après coup (fuite ultérieure sur un autre site).
     */
    #[ORM\Column]
    private bool $motDePasseExpose = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $motDePasseVerifieLe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email ?: null;

        return $this;
    }

    /**
     * Un mineur géré par un responsable légal n'a pas de compte de connexion propre.
     */
    public function aUnCompte(): bool
    {
        return null !== $this->email;
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

    public function isCordeur(): bool
    {
        return in_array(self::ROLE_CORDEUR, $this->roles, true);
    }

    public function isStock(): bool
    {
        return in_array(self::ROLE_STOCK, $this->roles, true);
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

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function getGenreLabel(): ?string
    {
        return self::GENRES[$this->genre] ?? null;
    }

    public function setGenre(?string $genre): static
    {
        $this->genre = in_array($genre, [self::GENRE_HOMME, self::GENRE_FEMME], true) ? $genre : null;

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

    public function getClassementSimple(): ?string
    {
        return $this->classementSimple;
    }

    public function setClassementSimple(?string $classementSimple): static
    {
        $this->classementSimple = $classementSimple;

        return $this;
    }

    public function getClassementDouble(): ?string
    {
        return $this->classementDouble;
    }

    public function setClassementDouble(?string $classementDouble): static
    {
        $this->classementDouble = $classementDouble;

        return $this;
    }

    public function getClassementMixte(): ?string
    {
        return $this->classementMixte;
    }

    public function setClassementMixte(?string $classementMixte): static
    {
        $this->classementMixte = $classementMixte;

        return $this;
    }

    /**
     * Meilleur classement du licencié tous tableaux confondus (simple/double/mixte), selon le rang officiel FFBaD.
     */
    public function getMeilleurClassement(): ?string
    {
        $meilleur = null;
        $meilleurRang = -1;

        foreach ([$this->classementSimple, $this->classementDouble, $this->classementMixte] as $classement) {
            $rang = ClassementFfbad::rang($classement);
            if ($rang > $meilleurRang) {
                $meilleurRang = $rang;
                $meilleur = $classement;
            }
        }

        return $meilleur;
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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getDesactiveLe(): ?\DateTimeImmutable
    {
        return $this->desactiveLe;
    }

    public function setDesactiveLe(?\DateTimeImmutable $desactiveLe): static
    {
        $this->desactiveLe = $desactiveLe;

        return $this;
    }

    public function isAnonymise(): bool
    {
        return $this->anonymise;
    }

    public function setAnonymise(bool $anonymise): static
    {
        $this->anonymise = $anonymise;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getAge(): ?int
    {
        if (!$this->dateNaissance) {
            return null;
        }

        return $this->dateNaissance->diff(new \DateTimeImmutable())->y;
    }

    /**
     * Catégorie d'âge du licencié (Creneau::CATEGORIE_*), ou null si la date de naissance est inconnue.
     */
    public function getCategorie(): ?string
    {
        $age = $this->getAge();
        if (null === $age) {
            return null;
        }

        return $age < 18 ? Creneau::CATEGORIE_ENFANT : Creneau::CATEGORIE_ADULTE;
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

    public function estMineur(): bool
    {
        $age = $this->getAge();

        return null !== $age && $age < 18;
    }

    public function getResponsableLegal1(): ?self
    {
        return $this->responsableLegal1;
    }

    public function setResponsableLegal1(?self $responsableLegal1): static
    {
        $this->responsableLegal1 = $responsableLegal1;

        return $this;
    }

    public function getResponsableLegal2(): ?self
    {
        return $this->responsableLegal2;
    }

    public function setResponsableLegal2(?self $responsableLegal2): static
    {
        $this->responsableLegal2 = $responsableLegal2;

        return $this;
    }

    /**
     * @return list<self>
     */
    public function getResponsablesLegaux(): array
    {
        return array_values(array_filter([$this->responsableLegal1, $this->responsableLegal2]));
    }

    public function estResponsableDe(?self $licencie): bool
    {
        if (null === $licencie) {
            return false;
        }

        return $licencie->responsableLegal1 === $this || $licencie->responsableLegal2 === $this;
    }

    public function getPersonnesAutoriseesRecuperation(): ?string
    {
        return $this->personnesAutoriseesRecuperation;
    }

    public function setPersonnesAutoriseesRecuperation(?string $personnesAutoriseesRecuperation): static
    {
        $this->personnesAutoriseesRecuperation = $personnesAutoriseesRecuperation;

        return $this;
    }

    public function getContactUrgenceNom(): ?string
    {
        return $this->contactUrgenceNom;
    }

    public function setContactUrgenceNom(?string $contactUrgenceNom): static
    {
        $this->contactUrgenceNom = $contactUrgenceNom;

        return $this;
    }

    public function getContactUrgenceTelephone(): ?string
    {
        return $this->contactUrgenceTelephone;
    }

    public function setContactUrgenceTelephone(?string $contactUrgenceTelephone): static
    {
        $this->contactUrgenceTelephone = $contactUrgenceTelephone;

        return $this;
    }

    public function isAutorisationSortieSeul(): bool
    {
        return $this->autorisationSortieSeul;
    }

    public function setAutorisationSortieSeul(bool $autorisationSortieSeul): static
    {
        $this->autorisationSortieSeul = $autorisationSortieSeul;

        return $this;
    }

    public function isDroitImage(): bool
    {
        return $this->droitImage;
    }

    public function setDroitImage(bool $droitImage): static
    {
        $this->droitImage = $droitImage;

        return $this;
    }

    public function getInformationsSante(): ?string
    {
        return $this->informationsSante;
    }

    public function setInformationsSante(?string $informationsSante): static
    {
        $this->informationsSante = $informationsSante;

        return $this;
    }

    public function isConsentementDonneesSante(): bool
    {
        return $this->consentementDonneesSante;
    }

    public function setConsentementDonneesSante(bool $consentementDonneesSante): static
    {
        $this->consentementDonneesSante = $consentementDonneesSante;

        return $this;
    }

    public function isNotificationsActivees(): bool
    {
        return $this->notificationsActivees;
    }

    public function setNotificationsActivees(bool $notificationsActivees): static
    {
        $this->notificationsActivees = $notificationsActivees;

        return $this;
    }

    public function getEquipePreferee(): ?Equipe
    {
        return $this->equipePreferee;
    }

    public function setEquipePreferee(?Equipe $equipePreferee): static
    {
        $this->equipePreferee = $equipePreferee;

        return $this;
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->emailAuthEnabled;
    }

    public function setEmailAuthEnabled(bool $emailAuthEnabled): static
    {
        $this->emailAuthEnabled = $emailAuthEnabled;

        return $this;
    }

    public function getEmailAuthRecipient(): string
    {
        return (string) $this->email;
    }

    public function getEmailAuthCode(): ?string
    {
        return $this->emailAuthCode;
    }

    public function setEmailAuthCode(string $authCode): void
    {
        $this->emailAuthCode = $authCode;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpAuthEnabled && null !== $this->totpSecret;
    }

    public function setTotpAuthEnabled(bool $totpAuthEnabled): static
    {
        $this->totpAuthEnabled = $totpAuthEnabled;

        return $this;
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->email;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function isMotDePasseExpose(): bool
    {
        return $this->motDePasseExpose;
    }

    public function setMotDePasseExpose(bool $motDePasseExpose): static
    {
        $this->motDePasseExpose = $motDePasseExpose;

        return $this;
    }

    public function getMotDePasseVerifieLe(): ?\DateTimeImmutable
    {
        return $this->motDePasseVerifieLe;
    }

    public function setMotDePasseVerifieLe(?\DateTimeImmutable $motDePasseVerifieLe): static
    {
        $this->motDePasseVerifieLe = $motDePasseVerifieLe;

        return $this;
    }

    public function getSuppressionDemandeeLe(): ?\DateTimeImmutable
    {
        return $this->suppressionDemandeeLe;
    }

    public function setSuppressionDemandeeLe(?\DateTimeImmutable $suppressionDemandeeLe): static
    {
        $this->suppressionDemandeeLe = $suppressionDemandeeLe;

        return $this;
    }
}
