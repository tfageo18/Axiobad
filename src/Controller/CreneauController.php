<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Creneau;
use App\Entity\CreneauOuverture;
use App\Entity\Gymnase;
use App\Entity\Licencie;
use App\Entity\Presence;
use App\Repository\CreneauOuvertureRepository;
use App\Repository\CreneauRepository;
use App\Repository\GymnaseRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/creneaux')]
class CreneauController extends AbstractController
{
    #[Route('', name: 'app_creneau_index', methods: ['GET'])]
    public function index(Request $request, CreneauRepository $creneauRepository): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $toutAfficher = $request->query->getBoolean('tous') || $this->isGranted('ROLE_BUREAU');

        $tousLesCreneaux = $creneauRepository->findAll();
        $creneaux = $toutAfficher
            ? $tousLesCreneaux
            : array_values(array_filter($tousLesCreneaux, static fn (Creneau $c) => $c->correspondA($licencie)));

        return $this->render('creneau/index.html.twig', [
            'creneaux' => $creneaux,
            'toutAfficher' => $toutAfficher,
        ]);
    }

    #[Route('/nouveau', name: 'app_creneau_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function new(Request $request, EntityManagerInterface $entityManager, GymnaseRepository $gymnaseRepository, LicencieRepository $licencieRepository): Response
    {
        if ($request->isMethod('POST')) {
            $creneau = new Creneau();
            if (!$this->remplirDepuisRequete($creneau, $request, $gymnaseRepository, $licencieRepository)) {
                return $this->redirectToRoute('app_creneau_new');
            }

            $entityManager->persist($creneau);
            $entityManager->flush();

            $this->addFlash('success', 'Créneau créé.');

            return $this->redirectToRoute('app_creneau_index');
        }

        return $this->render('creneau/form.html.twig', [
            'creneau' => null,
            'gymnases' => $gymnaseRepository->findAll(),
            'entraineurs' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_creneau_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function edit(Request $request, Creneau $creneau, EntityManagerInterface $entityManager, GymnaseRepository $gymnaseRepository, LicencieRepository $licencieRepository): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->remplirDepuisRequete($creneau, $request, $gymnaseRepository, $licencieRepository)) {
                return $this->redirectToRoute('app_creneau_edit', ['id' => $creneau->getId()]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Créneau modifié.');

            return $this->redirectToRoute('app_creneau_index');
        }

        return $this->render('creneau/form.html.twig', [
            'creneau' => $creneau,
            'gymnases' => $gymnaseRepository->findAll(),
            'entraineurs' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_creneau_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function delete(Request $request, Creneau $creneau, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-creneau-'.$creneau->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($creneau);
            $entityManager->flush();
            $this->addFlash('success', 'Créneau supprimé.');
        }

        return $this->redirectToRoute('app_creneau_index');
    }

    #[Route('/{id}/detail', name: 'app_creneau_detail', methods: ['GET'])]
    public function detail(
        Request $request,
        Creneau $creneau,
        LicencieRepository $licencieRepository,
        PresenceRepository $presenceRepository,
        CreneauOuvertureRepository $ouvertureRepository,
    ): Response {
        $date = new \DateTimeImmutable((string) $request->query->get('date', 'today'));

        $participants = array_values(array_filter($licencieRepository->findAll(), static fn (Licencie $l) => $creneau->correspondA($l)));

        $presencesParLicencie = [];
        foreach ($presenceRepository->findPourCreneauEtDate($creneau, $date) as $presence) {
            $presencesParLicencie[$presence->getLicencie()->getId()] = $presence;
        }

        $viennent = [];
        $neViennentPas = [];
        $sansReponse = [];
        foreach ($participants as $participant) {
            $presence = $presencesParLicencie[$participant->getId()] ?? null;
            if (null === $presence) {
                $sansReponse[] = $participant;
            } elseif ($presence->isPresent()) {
                $viennent[] = $participant;
            } else {
                $neViennentPas[] = $participant;
            }
        }

        return $this->render('creneau/detail.html.twig', [
            'creneau' => $creneau,
            'date' => $date,
            'ouverture' => $ouvertureRepository->findOneByCreneauEtDate($creneau, $date),
            'viennent' => $viennent,
            'neViennentPas' => $neViennentPas,
            'sansReponse' => $sansReponse,
            'maPresence' => $presencesParLicencie[$this->getUser()->getId()] ?? null,
        ]);
    }

    #[Route('/{id}/presence', name: 'app_creneau_presence', methods: ['POST'])]
    public function presence(Request $request, Creneau $creneau, EntityManagerInterface $entityManager, PresenceRepository $presenceRepository): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        $presence = $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date)
            ?? (new Presence())->setCreneau($creneau)->setLicencie($licencie)->setDate($date);

        $presence->setPresent('1' === $request->request->get('present'));

        $entityManager->persist($presence);
        $entityManager->flush();

        $this->addFlash('success', 'Réponse enregistrée.');

        if ($retour = $request->request->get('retour')) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('app_calendrier', ['semaine' => $request->request->get('semaine')]);
    }

    #[Route('/{id}/ouverture', name: 'app_creneau_ouverture', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function ouverture(Request $request, Creneau $creneau, EntityManagerInterface $entityManager, CreneauOuvertureRepository $ouvertureRepository, LicencieRepository $licencieRepository): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        $ouverture = $ouvertureRepository->findOneByCreneauEtDate($creneau, $date)
            ?? (new CreneauOuverture())->setCreneau($creneau)->setDate($date);

        $ouvreId = $request->request->get('licencieOuverture');
        $fermeId = $request->request->get('licencieFermeture');

        $ouverture->setLicencieOuverture($ouvreId ? $licencieRepository->find($ouvreId) : null);
        $ouverture->setLicencieFermeture($fermeId ? $licencieRepository->find($fermeId) : null);

        $entityManager->persist($ouverture);
        $entityManager->flush();

        $this->addFlash('success', 'Ouverture/fermeture mises à jour.');

        return $this->redirectToRoute('app_calendrier', ['mois' => $request->request->get('mois')]);
    }

    private function remplirDepuisRequete(Creneau $creneau, Request $request, GymnaseRepository $gymnaseRepository, LicencieRepository $licencieRepository): bool
    {
        $gymnase = $gymnaseRepository->find($request->request->get('gymnase'));
        if (!$gymnase instanceof Gymnase) {
            $this->addFlash('error', 'Gymnase invalide.');

            return false;
        }

        $encadre = (bool) $request->request->get('encadre');
        $entraineur = null;
        if ($encadre) {
            $entraineur = $licencieRepository->find($request->request->get('entraineur'));
            if (!$entraineur instanceof Licencie || !$entraineur->isEntraineur()) {
                $this->addFlash('error', 'Un entraîneur valide est requis pour un créneau encadré.');

                return false;
            }
        }

        $categorie = (string) $request->request->get('categorie');
        if (!in_array($categorie, [Creneau::CATEGORIE_ADULTE, Creneau::CATEGORIE_ENFANT], true)) {
            $categorie = Creneau::CATEGORIE_ADULTE;
        }

        $classementMinimumRaw = (string) $request->request->get('classementMinimum');
        $classementMinimum = in_array($classementMinimumRaw, ClassementFfbad::CODES, true) ? $classementMinimumRaw : null;

        $recurrenceDebutRaw = (string) $request->request->get('recurrenceDebut');
        $recurrenceFinRaw = (string) $request->request->get('recurrenceFin');

        $creneau
            ->setNom((string) $request->request->get('nom'))
            ->setGymnase($gymnase)
            ->setJourSemaine((string) $request->request->get('jourSemaine'))
            ->setHeureDebut(new \DateTimeImmutable((string) $request->request->get('heureDebut')))
            ->setHeureFin(new \DateTimeImmutable((string) $request->request->get('heureFin')))
            ->setEncadre($encadre)
            ->setEntraineur($entraineur)
            ->setCategorie($categorie)
            ->setClassementMinimum($classementMinimum)
            ->setRecurrenceDebut($recurrenceDebutRaw ? new \DateTimeImmutable($recurrenceDebutRaw) : null)
            ->setRecurrenceFin($recurrenceFinRaw ? new \DateTimeImmutable($recurrenceFinRaw) : null);

        return true;
    }
}
