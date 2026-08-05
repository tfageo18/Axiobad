<?php

namespace App\Controller;

use App\Repository\LicencieRepository;
use App\Repository\NotificationEnvoyeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications-historique')]
#[IsGranted('ROLE_BUREAU')]
class NotificationHistoriqueController extends AbstractController
{
    #[Route('', name: 'app_notification_historique_index', methods: ['GET'])]
    public function index(Request $request, NotificationEnvoyeeRepository $notificationEnvoyeeRepository, LicencieRepository $licencieRepository): Response
    {
        $destinataireId = $request->query->get('destinataire') ? (int) $request->query->get('destinataire') : null;

        return $this->render('notification_historique/index.html.twig', [
            'entrees' => $notificationEnvoyeeRepository->rechercher($destinataireId),
            'licencies' => $licencieRepository->findBy([], ['nom' => 'ASC']),
            'destinataireChoisi' => $destinataireId,
        ]);
    }
}
