<?php

namespace App\Service;

use App\Entity\Licencie;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Récupère le classement d'un licencié auprès de l'API de la Fédération Française de Badminton.
 */
class FfbadClassementService
{
    public function __construct(
        private readonly HttpClientInterface $ffbadClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function mettreAJourClassement(Licencie $licencie): bool
    {
        if (!$licencie->getNumeroLicence()) {
            return false;
        }

        try {
            $response = $this->ffbadClient->request('GET', sprintf('/licence/%s/classement', $licencie->getNumeroLicence()));
            $data = $response->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible de récupérer le classement FFBaD pour le licencié {id}: {message}', [
                'id' => $licencie->getId(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $licencie->setClassementSimple($data['simple'] ?? null);
        $licencie->setClassementDouble($data['double'] ?? null);
        $licencie->setClassementMixte($data['mixte'] ?? null);
        $licencie->setClassementMisAJourLe(new \DateTimeImmutable());

        return true;
    }
}
