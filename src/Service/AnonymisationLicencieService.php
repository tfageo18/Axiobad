<?php

namespace App\Service;

use App\Entity\Licencie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Anonymise un licencié (RGPD art. 17) : à la différence d'une suppression, la ligne et les
 * données comptables qui lui sont liées (adhésions, paiements) sont conservées — seule son
 * identité et ses données personnelles sont effacées, de façon irréversible.
 */
class AnonymisationLicencieService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function anonymiser(Licencie $licencie): void
    {
        $licencie
            ->setPrenom('Ancien')
            ->setNom(sprintf('licencié #%d', $licencie->getId()))
            ->setEmail(null)
            ->setTelephone(null)
            ->setPhoto(null)
            ->setGenre(null)
            ->setDateNaissance(null)
            ->setNumeroLicence(null)
            ->setPersonnesAutoriseesRecuperation(null)
            ->setContactUrgenceNom(null)
            ->setContactUrgenceTelephone(null)
            ->setInformationsSante(null)
            ->setConsentementDonneesSante(false)
            ->setResponsableLegal1(null)
            ->setResponsableLegal2(null)
            ->setActif(false)
            ->setAnonymise(true)
            ->clearActivationToken();

        $licencie->setPassword($this->passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));

        $this->entityManager->flush();
    }
}
