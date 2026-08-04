<?php

namespace App\Controller;

use App\Repository\JournalAuditRepository;
use App\Repository\LicencieRepository;
use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/journal-audit')]
#[IsGranted('ROLE_BUREAU')]
class JournalAuditController extends AbstractController
{
    #[Route('', name: 'app_journal_audit_index', methods: ['GET'])]
    public function index(Request $request, JournalAuditRepository $journalAuditRepository, LicencieRepository $licencieRepository): Response
    {
        $action = (string) $request->query->get('action') ?: null;
        $utilisateurId = $request->query->get('utilisateur') ? (int) $request->query->get('utilisateur') : null;

        return $this->render('journal_audit/index.html.twig', [
            'entrees' => $journalAuditRepository->rechercher($action, $utilisateurId),
            'actionsDisponibles' => $journalAuditRepository->findActionsDistinctes(),
            'libellesActions' => AuditLogger::LIBELLES,
            'licencies' => $licencieRepository->findBy([], ['nom' => 'ASC']),
            'actionChoisie' => $action,
            'utilisateurChoisi' => $utilisateurId,
        ]);
    }
}
