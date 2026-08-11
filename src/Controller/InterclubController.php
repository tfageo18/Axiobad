<?php

namespace App\Controller;

use App\Entity\Convocation;
use App\Entity\Equipe;
use App\Entity\Licencie;
use App\Entity\MatchInterclub;
use App\Entity\RencontreInterclub;
use App\Repository\EquipeRepository;
use App\Repository\GymnaseRepository;
use App\Repository\LicencieRepository;
use App\Repository\MatchInterclubRepository;
use App\Repository\RencontreInterclubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/interclubs')]
class InterclubController extends AbstractController
{
    #[Route('', name: 'app_interclub_index', methods: ['GET'])]
    public function index(Request $request, RencontreInterclubRepository $rencontreRepository, EquipeRepository $equipeRepository): Response
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();
        $mesEquipes = $user ? $equipeRepository->findByMembre($user) : [];

        if ($request->query->has('equipe')) {
            // L'utilisateur a explicitement soumis le filtre (une valeur vide = "Toutes les équipes").
            $equipeFiltreId = $request->query->get('equipe');
        } elseif ($user && $user->getEquipePreferee()) {
            $equipeFiltreId = (string) $user->getEquipePreferee()->getId();
        } elseif (1 === count($mesEquipes)) {
            $equipeFiltreId = (string) $mesEquipes[0]->getId();
        } else {
            $equipeFiltreId = '';
        }

        $criteres = $equipeFiltreId ? ['equipe' => $equipeFiltreId] : [];

        return $this->render('interclub/index.html.twig', [
            'rencontres' => $rencontreRepository->findBy($criteres, ['dateRencontre' => 'ASC']),
            'equipes' => $equipeRepository->findBy([], ['nom' => 'ASC']),
            'equipeFiltreId' => $equipeFiltreId,
        ]);
    }

    #[Route('/nouveau', name: 'app_interclub_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager, EquipeRepository $equipeRepository, LicencieRepository $licencieRepository, GymnaseRepository $gymnaseRepository): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('interclub-new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_interclub_new');
            }

            $rencontre = new RencontreInterclub();
            $this->hydrater($rencontre, $request, $equipeRepository, $licencieRepository, $gymnaseRepository);

            $entityManager->persist($rencontre);
            $entityManager->flush();

            $this->addFlash('success', 'Rencontre créée.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        return $this->render('interclub/form.html.twig', [
            'rencontre' => null,
            'equipes' => $equipeRepository->findAll(),
            'licencies' => $licencieRepository->findAll(),
            'gymnases' => $gymnaseRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_interclub_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(RencontreInterclub $rencontre, LicencieRepository $licencieRepository): Response
    {
        /** @var Licencie|null $user */
        $user = $this->getUser();

        $convoques = array_map(static fn (Convocation $c) => $c->getLicencie(), $rencontre->getConvocations()->toArray());
        $membresConvocables = array_filter(
            $rencontre->getEquipe()->getMembres()->toArray(),
            static fn (Licencie $l) => !in_array($l, $convoques, true)
        );

        return $this->render('interclub/detail.html.twig', [
            'rencontre' => $rencontre,
            'maConvocation' => $rencontre->getConvocationDe($user),
            'membresConvocables' => $membresConvocables,
            'membresEquipe' => $rencontre->getEquipe()->getMembres(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_interclub_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function edit(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager, EquipeRepository $equipeRepository, LicencieRepository $licencieRepository, GymnaseRepository $gymnaseRepository): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('interclub-edit-'.$rencontre->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_interclub_edit', ['id' => $rencontre->getId()]);
            }

            $this->hydrater($rencontre, $request, $equipeRepository, $licencieRepository, $gymnaseRepository);
            $entityManager->flush();

            $this->addFlash('success', 'Rencontre modifiée.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        return $this->render('interclub/form.html.twig', [
            'rencontre' => $rencontre,
            'equipes' => $equipeRepository->findAll(),
            'licencies' => $licencieRepository->findAll(),
            'gymnases' => $gymnaseRepository->findAll(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_interclub_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-rencontre-'.$rencontre->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($rencontre);
            $entityManager->flush();
            $this->addFlash('success', 'Rencontre supprimée.');
        }

        return $this->redirectToRoute('app_interclub_index');
    }

    #[Route('/{id}/convocations', name: 'app_interclub_convoquer', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function convoquer(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $licencie = $licencieRepository->find($request->request->get('licencie'));
        if (!$licencie || !$rencontre->getEquipe()->getMembres()->contains($licencie) || $rencontre->getConvocationDe($licencie)) {
            $this->addFlash('error', 'Convocation impossible.');

            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        $convocation = (new Convocation())
            ->setRencontre($rencontre)
            ->setLicencie($licencie);

        $entityManager->persist($convocation);
        $entityManager->flush();

        $this->addFlash('success', 'Joueur convoqué.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/convocations/{convocationId}/retirer', name: 'app_interclub_retirer_convocation', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function retirerConvocation(Request $request, RencontreInterclub $rencontre, int $convocationId, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('retirer-convocation-'.$convocationId, (string) $request->request->get('_token'))) {
            foreach ($rencontre->getConvocations() as $convocation) {
                if ($convocation->getId() === $convocationId) {
                    $entityManager->remove($convocation);
                    $entityManager->flush();
                    $this->addFlash('success', 'Convocation retirée.');

                    break;
                }
            }
        }

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/convocations/{convocationId}/repondre', name: 'app_interclub_repondre', methods: ['POST'])]
    public function repondre(Request $request, RencontreInterclub $rencontre, int $convocationId, EntityManagerInterface $entityManager): Response
    {
        $convocation = null;
        foreach ($rencontre->getConvocations() as $candidate) {
            if ($candidate->getId() === $convocationId) {
                $convocation = $candidate;

                break;
            }
        }

        if (!$convocation) {
            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        if ($convocation->getLicencie() !== $this->getUser() && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }

        $convocation->setPresent('1' === $request->request->get('present'));
        $entityManager->flush();

        $this->addFlash('success', 'Réponse enregistrée.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    private function hydrater(RencontreInterclub $rencontre, Request $request, EquipeRepository $equipeRepository, LicencieRepository $licencieRepository, GymnaseRepository $gymnaseRepository): void
    {
        $equipe = $equipeRepository->find($request->request->get('equipe'));
        $scoreEquipe = $request->request->get('scoreEquipe');
        $scoreAdversaire = $request->request->get('scoreAdversaire');
        $heureRdv = (string) $request->request->get('heureRdv');
        $capitaineId = $request->request->get('capitaineRencontre');
        $domicile = (bool) $request->request->get('domicile');
        $gymnase = $domicile ? $gymnaseRepository->find($request->request->get('gymnase')) : null;
        $lieu = (string) $request->request->get('lieu');

        $rencontre
            ->setEquipe($equipe instanceof Equipe ? $equipe : null)
            ->setJournee((int) $request->request->get('journee'))
            ->setDateRencontre(new \DateTimeImmutable((string) $request->request->get('dateRencontre')))
            ->setGymnase($gymnase)
            ->setLieu($domicile ? ($gymnase ? null : ($lieu ?: null)) : ($lieu ?: null))
            ->setAdversaire((string) $request->request->get('adversaire'))
            ->setScoreEquipe(null !== $scoreEquipe && '' !== $scoreEquipe ? (int) $scoreEquipe : null)
            ->setScoreAdversaire(null !== $scoreAdversaire && '' !== $scoreAdversaire ? (int) $scoreAdversaire : null)
            ->setDomicile($domicile)
            ->setHeureRdv($heureRdv ? new \DateTimeImmutable($heureRdv) : null)
            ->setCapitaineRencontre($capitaineId ? $licencieRepository->find($capitaineId) : null)
            ->setCovoiturage((string) $request->request->get('covoiturage') ?: null);
    }

    #[Route('/{id}/matchs', name: 'app_interclub_match_new', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function ajouterMatch(Request $request, RencontreInterclub $rencontre, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $joueur1Id = $request->request->get('joueur1');
        $joueur2Id = $request->request->get('joueur2');
        $gagne = $request->request->get('gagne');

        $match = (new MatchInterclub())
            ->setRencontre($rencontre)
            ->setType((string) $request->request->get('type'))
            ->setJoueur1($joueur1Id ? $licencieRepository->find($joueur1Id) : null)
            ->setJoueur2($joueur2Id ? $licencieRepository->find($joueur2Id) : null)
            ->setAdversaires((string) $request->request->get('adversaires') ?: null)
            ->setScore((string) $request->request->get('score') ?: null)
            ->setGagne('' !== $gagne ? '1' === $gagne : null);

        $entityManager->persist($match);
        $entityManager->flush();

        $this->addFlash('success', 'Match ajouté.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/matchs/{matchId}/modifier', name: 'app_interclub_match_edit', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function modifierMatch(Request $request, RencontreInterclub $rencontre, int $matchId, MatchInterclubRepository $matchRepository, EntityManagerInterface $entityManager, LicencieRepository $licencieRepository): Response
    {
        $match = $matchRepository->find($matchId);
        if (!$match || $match->getRencontre() !== $rencontre) {
            return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
        }

        $joueur1Id = $request->request->get('joueur1');
        $joueur2Id = $request->request->get('joueur2');
        $gagne = $request->request->get('gagne');

        $match
            ->setType((string) $request->request->get('type'))
            ->setJoueur1($joueur1Id ? $licencieRepository->find($joueur1Id) : null)
            ->setJoueur2($joueur2Id ? $licencieRepository->find($joueur2Id) : null)
            ->setAdversaires((string) $request->request->get('adversaires') ?: null)
            ->setScore((string) $request->request->get('score') ?: null)
            ->setGagne('' !== $gagne ? '1' === $gagne : null);

        $entityManager->flush();

        $this->addFlash('success', 'Match modifié.');

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/{id}/matchs/{matchId}/supprimer', name: 'app_interclub_match_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function supprimerMatch(Request $request, RencontreInterclub $rencontre, int $matchId, MatchInterclubRepository $matchRepository, EntityManagerInterface $entityManager): Response
    {
        $match = $matchRepository->find($matchId);
        if ($match && $match->getRencontre() === $rencontre && $this->isCsrfTokenValid('delete-match-'.$matchId, (string) $request->request->get('_token'))) {
            $entityManager->remove($match);
            $entityManager->flush();
            $this->addFlash('success', 'Match supprimé.');
        }

        return $this->redirectToRoute('app_interclub_detail', ['id' => $rencontre->getId()]);
    }

    #[Route('/statistiques', name: 'app_interclub_statistiques', methods: ['GET'])]
    public function statistiques(EquipeRepository $equipeRepository, RencontreInterclubRepository $rencontreRepository, MatchInterclubRepository $matchRepository): Response
    {
        $statsEquipes = [];
        foreach ($equipeRepository->findAll() as $equipe) {
            $rencontres = array_values(array_filter(
                $rencontreRepository->findBy(['equipe' => $equipe]),
                static fn (RencontreInterclub $r) => $r->aUnScore()
            ));
            $gagnees = count(array_filter($rencontres, static fn (RencontreInterclub $r) => $r->getScoreEquipe() > $r->getScoreAdversaire()));
            $perdues = count(array_filter($rencontres, static fn (RencontreInterclub $r) => $r->getScoreEquipe() < $r->getScoreAdversaire()));
            $nulles = count($rencontres) - $gagnees - $perdues;

            if (count($rencontres) > 0) {
                $statsEquipes[] = [
                    'equipe' => $equipe,
                    'jouees' => count($rencontres),
                    'gagnees' => $gagnees,
                    'perdues' => $perdues,
                    'nulles' => $nulles,
                ];
            }
        }

        $statsJoueurs = [];
        foreach ($matchRepository->findAll() as $match) {
            foreach ($match->getJoueurs() as $joueur) {
                $id = $joueur->getId();
                $statsJoueurs[$id] ??= ['joueur' => $joueur, 'joues' => 0, 'gagnes' => 0, 'perdus' => 0];
                ++$statsJoueurs[$id]['joues'];
                if (true === $match->getGagne()) {
                    ++$statsJoueurs[$id]['gagnes'];
                } elseif (false === $match->getGagne()) {
                    ++$statsJoueurs[$id]['perdus'];
                }
            }
        }
        usort($statsJoueurs, static fn (array $a, array $b) => $b['joues'] <=> $a['joues']);

        return $this->render('interclub/statistiques.html.twig', [
            'statsEquipes' => $statsEquipes,
            'statsJoueurs' => array_values($statsJoueurs),
        ]);
    }
}
