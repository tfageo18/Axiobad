<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Enregistre/supprime les abonnements Web Push des licenciés depuis leurs appareils.
 */
class PushSubscriptionController extends AbstractController
{
    #[Route('/push/abonner', name: 'app_push_abonner', methods: ['POST'])]
    public function abonner(
        Request $request,
        EntityManagerInterface $entityManager,
        PushSubscriptionRepository $subscriptionRepository,
    ): JsonResponse {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        $donnees = json_decode($request->getContent(), true);
        $endpoint = $donnees['endpoint'] ?? null;
        $p256dh = $donnees['keys']['p256dh'] ?? null;
        $auth = $donnees['keys']['auth'] ?? null;

        if (!$endpoint || !$p256dh || !$auth) {
            return new JsonResponse(['erreur' => 'Abonnement invalide.'], 400);
        }

        $abonnement = $subscriptionRepository->findOneByEndpoint($endpoint);
        if (!$abonnement) {
            $abonnement = new PushSubscription();
            $abonnement->setEndpoint($endpoint);
        }

        $abonnement->setLicencie($licencie);
        $abonnement->setP256dhKey($p256dh);
        $abonnement->setAuthToken($auth);

        $entityManager->persist($abonnement);
        $entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/push/desabonner', name: 'app_push_desabonner', methods: ['POST'])]
    public function desabonner(
        Request $request,
        EntityManagerInterface $entityManager,
        PushSubscriptionRepository $subscriptionRepository,
    ): JsonResponse {
        $donnees = json_decode($request->getContent(), true);
        $endpoint = $donnees['endpoint'] ?? null;

        if ($endpoint) {
            $abonnement = $subscriptionRepository->findOneByEndpoint($endpoint);
            if ($abonnement && $abonnement->getLicencie() === $this->getUser()) {
                $entityManager->remove($abonnement);
                $entityManager->flush();
            }
        }

        return new JsonResponse(['ok' => true]);
    }
}
