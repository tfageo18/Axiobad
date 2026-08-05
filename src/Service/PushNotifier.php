<?php

namespace App\Service;

use App\Entity\Licencie;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Envoie des notifications push navigateur (Web Push) aux appareils sur lesquels un licencié a
 * activé les notifications push depuis la PWA. Best-effort : un abonnement expiré ou invalide est
 * silencieusement supprimé, jamais une exception qui remonterait à l'appelant.
 */
class PushNotifier
{
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ?string $vapidPublicKey,
        private readonly ?string $vapidPrivateKey,
        private readonly string $vapidSubject,
    ) {
    }

    public function estConfigure(): bool
    {
        return (bool) $this->vapidPublicKey && (bool) $this->vapidPrivateKey;
    }

    /**
     * @return bool true si la notification a été livrée avec succès à au moins un appareil
     */
    public function notifier(Licencie $licencie, string $titre, string $corps, ?string $url = null): bool
    {
        if (!$this->estConfigure()) {
            return false;
        }

        $abonnements = $this->subscriptionRepository->findPourLicencie($licencie);
        if (!$abonnements) {
            return false;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $titre,
            'body' => $corps,
            'url' => $url ?? '/',
        ], JSON_THROW_ON_ERROR);

        foreach ($abonnements as $abonnement) {
            $subscription = Subscription::create([
                'endpoint' => $abonnement->getEndpoint(),
                'publicKey' => $abonnement->getP256dhKey(),
                'authToken' => $abonnement->getAuthToken(),
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        $succes = false;

        foreach ($webPush->flush() as $rapport) {
            if ($rapport->isSuccess()) {
                $succes = true;

                continue;
            }

            if ($rapport->isSubscriptionExpired()) {
                $abonnementExpire = $this->subscriptionRepository->findOneByEndpoint($rapport->getEndpoint());
                if ($abonnementExpire) {
                    $this->entityManager->remove($abonnementExpire);
                }

                continue;
            }

            $this->logger->warning('Échec d\'envoi de notification push à {endpoint} : {message}', [
                'endpoint' => $rapport->getEndpoint(),
                'message' => $rapport->getReason(),
            ]);
        }

        $this->entityManager->flush();

        return $succes;
    }
}
