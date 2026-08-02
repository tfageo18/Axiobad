<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Creneau;
use App\Entity\CreneauOuverture;
use App\Entity\Gymnase;
use App\Entity\Licencie;
use App\Repository\CreneauOuvertureRepository;
use App\Repository\CreneauRepository;
use App\Repository\GymnaseRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Service\GestionInscriptionCreneau;
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
        if (!$this->isGranted('ROLE_BUREAU')) {
            $tousLesCreneaux = array_values(array_filter($tousLesCreneaux, static fn (Creneau $c) => $c->isActif()));
        }
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

    #[Route('/{id}/activer', name: 'app_creneau_toggle_actif', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function toggleActif(Creneau $creneau, EntityManagerInterface $entityManager): Response
    {
        $creneau->setActif(!$creneau->isActif());
        $entityManager->flush();

        $this->addFlash('success', $creneau->isActif() ? 'Créneau réactivé.' : 'Créneau désactivé.');

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

        $confirmes = [];
        $listeAttente = [];
        $enAttenteConfirmation = [];
        $neViennentPas = [];
        $sansReponse = [];
        foreach ($participants as $participant) {
            $presence = $presencesParLicencie[$participant->getId()] ?? null;
            if (null === $presence) {
                $sansReponse[] = $participant;
            } elseif (!$presence->isPresent()) {
                $neViennentPas[] = $participant;
            } elseif ($presence->estEnListeAttente()) {
                $listeAttente[] = ['licencie' => $participant, 'presence' => $presence];
            } elseif ($presence->estEnAttenteConfirmation()) {
                $enAttenteConfirmation[] = ['licencie' => $participant, 'presence' => $presence];
            } else {
                $confirmes[] = $participant;
            }
        }
        usort($listeAttente, static fn (array $a, array $b) => $a['presence']->getRepondule() <=> $b['presence']->getRepondule());

        return $this->render('creneau/detail.html.twig', [
            'creneau' => $creneau,
            'date' => $date,
            'ouverture' => $ouvertureRepository->findOneByCreneauEtDate($creneau, $date),
            'viennent' => $confirmes,
            'listeAttente' => $listeAttente,
            'enAttenteConfirmation' => $enAttenteConfirmation,
            'neViennentPas' => $neViennentPas,
            'sansReponse' => $sansReponse,
            'maPresence' => $presencesParLicencie[$this->getUser()->getId()] ?? null,
            'placesRestantes' => $creneau->getCapaciteMax() !== null ? max(0, $creneau->getCapaciteMax() - count($confirmes)) : null,
            'licenciesDisponibles' => $licencieRepository->findAll(),
        ]);
    }

    #[Route('/{id}/ics', name: 'app_creneau_ics', methods: ['GET'])]
    public function ics(Request $request, Creneau $creneau): Response
    {
        $date = new \DateTimeImmutable((string) $request->query->get('date', 'today'));

        $debut = $date->setTime((int) $creneau->getHeureDebut()->format('H'), (int) $creneau->getHeureDebut()->format('i'));
        $fin = $date->setTime((int) $creneau->getHeureFin()->format('H'), (int) $creneau->getHeureFin()->format('i'));

        $echapper = static fn (string $texte): string => str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $texte);

        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Axiobad//FR',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:creneau-'.$creneau->getId().'-'.$date->format('Ymd').'@axiobad',
            'DTSTAMP:'.(new \DateTimeImmutable())->format('Ymd\THis\Z'),
            'DTSTART:'.$debut->format('Ymd\THis'),
            'DTEND:'.$fin->format('Ymd\THis'),
            'SUMMARY:'.$echapper($creneau->getNom().' — '.$creneau->getActivite()),
            'LOCATION:'.$echapper($creneau->getGymnase()->getNom().', '.$creneau->getGymnase()->getAdresse()),
            'DESCRIPTION:'.$echapper('Créneau Axiobad : '.$creneau->getNom()),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $response = new Response(implode("\r\n", $lignes));
        $response->headers->set('Content-Type', 'text/calendar; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="creneau-'.$creneau->getId().'-'.$date->format('Y-m-d').'.ics"');

        return $response;
    }

    #[Route('/{id}/google-agenda', name: 'app_creneau_google_agenda', methods: ['GET'])]
    public function googleAgenda(Request $request, Creneau $creneau): Response
    {
        $date = new \DateTimeImmutable((string) $request->query->get('date', 'today'));

        $debut = $date->setTime((int) $creneau->getHeureDebut()->format('H'), (int) $creneau->getHeureDebut()->format('i'));
        $fin = $date->setTime((int) $creneau->getHeureFin()->format('H'), (int) $creneau->getHeureFin()->format('i'));

        $parametres = [
            'action' => 'TEMPLATE',
            'text' => $creneau->getNom().' — '.$creneau->getActivite(),
            'dates' => $debut->format('Ymd\THis').'/'.$fin->format('Ymd\THis'),
            'details' => 'Créneau Axiobad : '.$creneau->getNom(),
            'location' => $creneau->getGymnase()->getNom().', '.$creneau->getGymnase()->getAdresse(),
            'ctz' => 'Europe/Paris',
        ];

        return $this->redirect('https://calendar.google.com/calendar/render?'.http_build_query($parametres));
    }

    #[Route('/{id}/presence', name: 'app_creneau_presence', methods: ['POST'])]
    public function presence(Request $request, Creneau $creneau, GestionInscriptionCreneau $gestionInscriptionCreneau): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $date = new \DateTimeImmutable((string) $request->request->get('date'));
        $veutVenir = '1' === $request->request->get('present');

        $resultat = $gestionInscriptionCreneau->repondre($creneau, $licencie, $date, $veutVenir, $this->isGranted('ROLE_BUREAU'));

        if (!$resultat['ok']) {
            $this->addFlash('error', $resultat['erreur']);
        } else {
            $this->addFlash('success', 'Réponse enregistrée.');
        }

        if ($retour = $request->request->get('retour')) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('app_calendrier', ['semaine' => $request->request->get('semaine')]);
    }

    #[Route('/{id}/confirmer-promotion', name: 'app_creneau_confirmer_promotion', methods: ['POST'])]
    public function confirmerPromotion(Request $request, Creneau $creneau, GestionInscriptionCreneau $gestionInscriptionCreneau, PresenceRepository $presenceRepository): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        $presence = $presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $date);

        if ($presence && $gestionInscriptionCreneau->confirmerPromotion($presence)) {
            $this->addFlash('success', 'Inscription confirmée.');
        } else {
            $this->addFlash('error', 'Cette proposition de place a expiré ou n\'existe plus.');
        }

        if ($retour = $request->request->get('retour')) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('app_calendrier');
    }

    #[Route('/{id}/forcer-inscription', name: 'app_creneau_forcer_inscription', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function forcerInscription(Request $request, Creneau $creneau, GestionInscriptionCreneau $gestionInscriptionCreneau, LicencieRepository $licencieRepository): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));
        $licencie = $licencieRepository->find($request->request->get('licencie'));

        if (!$licencie instanceof Licencie) {
            $this->addFlash('error', 'Licencié invalide.');

            return $this->redirectToRoute('app_creneau_detail', ['id' => $creneau->getId(), 'date' => $date->format('Y-m-d')]);
        }

        $gestionInscriptionCreneau->forcerInscription($creneau, $licencie, $date);

        $this->addFlash('success', sprintf('%s a été inscrit(e) de force.', $licencie->getNomComplet()));

        return $this->redirectToRoute('app_creneau_detail', ['id' => $creneau->getId(), 'date' => $date->format('Y-m-d')]);
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

        $activite = trim((string) $request->request->get('activite')) ?: Creneau::ACTIVITE_BADMINTON;

        $classementMinimumRaw = (string) $request->request->get('classementMinimum');
        $classementMinimum = in_array($classementMinimumRaw, ClassementFfbad::CODES, true) ? $classementMinimumRaw : null;

        $recurrenceDebutRaw = (string) $request->request->get('recurrenceDebut');
        $recurrenceFinRaw = (string) $request->request->get('recurrenceFin');

        $capaciteMaxRaw = $request->request->get('capaciteMax');
        $delaiAnnulationRaw = $request->request->get('delaiAnnulationHeures');

        $creneau
            ->setNom((string) $request->request->get('nom'))
            ->setGymnase($gymnase)
            ->setJourSemaine((string) $request->request->get('jourSemaine'))
            ->setHeureDebut(new \DateTimeImmutable((string) $request->request->get('heureDebut')))
            ->setHeureFin(new \DateTimeImmutable((string) $request->request->get('heureFin')))
            ->setEncadre($encadre)
            ->setEntraineur($entraineur)
            ->setCategorie($categorie)
            ->setActivite($activite)
            ->setClassementMinimum($classementMinimum)
            ->setLoisir((bool) $request->request->get('loisir'))
            ->setCompetiteur((bool) $request->request->get('competiteur'))
            ->setOuvertExternes((bool) $request->request->get('ouvertExternes'))
            ->setOuvertAdos((bool) $request->request->get('ouvertAdos'))
            ->setRecurrenceDebut($recurrenceDebutRaw ? new \DateTimeImmutable($recurrenceDebutRaw) : null)
            ->setRecurrenceFin($recurrenceFinRaw ? new \DateTimeImmutable($recurrenceFinRaw) : null)
            ->setCapaciteMax(null !== $capaciteMaxRaw && '' !== $capaciteMaxRaw ? (int) $capaciteMaxRaw : null)
            ->setDelaiAnnulationHeures(null !== $delaiAnnulationRaw && '' !== $delaiAnnulationRaw ? (int) $delaiAnnulationRaw : null);

        return true;
    }
}
