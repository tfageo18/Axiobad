<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/presences')]
class PresenceController extends AbstractController
{
    private const MOIS_COURTS = [
        1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
    ];

    #[Route('', name: 'app_presence_index', methods: ['GET'])]
    public function index(LicencieRepository $licencieRepository, PresenceRepository $presenceRepository): Response
    {
        $this->refuserSiPasBureauNiEntraineur();

        $stats = [];
        foreach ($licencieRepository->findAll() as $licencie) {
            if ($licencie->getEmail() === Licencie::EMAIL_ADMIN_DEFAUT) {
                continue;
            }

            $presences = $presenceRepository->findBy(['licencie' => $licencie]);
            $total = count($presences);
            $venus = count(array_filter($presences, static fn ($p) => $p->isPresent()));

            $stats[] = [
                'licencie' => $licencie,
                'nombreSeances' => $venus,
                'nombreReponses' => $total,
                'taux' => $total > 0 ? round($venus / $total * 100) : null,
            ];
        }

        usort($stats, static fn ($a, $b) => ($b['taux'] ?? -1) <=> ($a['taux'] ?? -1));

        return $this->render('presence/index.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'app_presence_detail', methods: ['GET'])]
    public function detail(Licencie $licencie, PresenceRepository $presenceRepository, AuditLogger $auditLogger): Response
    {
        $this->refuserSiPasBureauNiEntraineur();

        if ($licencie->getInformationsSante()) {
            $auditLogger->log(AuditLogger::SANTE_CONSULTEE, 'Licencie', $licencie->getNomComplet());
        }

        $presences = $presenceRepository->findBy(['licencie' => $licencie], ['date' => 'DESC']);
        $total = count($presences);
        $venus = count(array_filter($presences, static fn ($p) => $p->isPresent()));

        // Répartition par mois sur les 6 derniers mois (glissants) pour le graphique.
        $mois = [];
        $curseur = new \DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; --$i) {
            $debutMois = $curseur->modify(sprintf('-%d months', $i));
            $cle = $debutMois->format('Y-m');
            $mois[$cle] = [
                'label' => self::MOIS_COURTS[(int) $debutMois->format('n')].' '.$debutMois->format('y'),
                'venus' => 0,
                'total' => 0,
            ];
        }
        foreach ($presences as $presence) {
            $cle = $presence->getDate()->format('Y-m');
            if (isset($mois[$cle])) {
                ++$mois[$cle]['total'];
                if ($presence->isPresent()) {
                    ++$mois[$cle]['venus'];
                }
            }
        }

        $mois = array_values($mois);
        $maxMois = max(1, ...array_map(static fn ($m) => $m['total'], $mois));

        return $this->render('presence/detail.html.twig', [
            'licencie' => $licencie,
            'presences' => $presences,
            'nombreSeances' => $venus,
            'nombreReponses' => $total,
            'taux' => $total > 0 ? round($venus / $total * 100) : null,
            'mois' => $mois,
            'maxMois' => $maxMois,
        ]);
    }

    private function refuserSiPasBureauNiEntraineur(): void
    {
        if (!$this->isGranted('ROLE_BUREAU') && !$this->isGranted('ROLE_ENTRAINEUR')) {
            throw $this->createAccessDeniedException();
        }
    }
}
