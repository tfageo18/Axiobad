<?php

namespace App\Controller;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Seance;
use App\Entity\SeanceSchema;
use App\Repository\SeanceRepository;
use App\Repository\SeanceSchemaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contenu des séances dirigées : saisi par un des entraîneurs du créneau (ou le bureau), une
 * séance par occurrence (créneau + date). Publiable aux licenciés inscrits à cette occurrence.
 */
#[Route('/creneaux/{id}/seances/{date}', name: 'app_seance_')]
class SeanceController extends AbstractController
{
    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Creneau $creneau, string $date, SeanceRepository $seanceRepository, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $seance = $seanceRepository->findOneByCreneauEtDate($creneau, $dateObjet) ?? (new Seance())->setCreneau($creneau)->setDate($dateObjet);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('seance-edit-'.$creneau->getId().'-'.$date, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_seance_edit', ['id' => $creneau->getId(), 'date' => $date]);
            }

            /** @var Licencie $redacteur */
            $redacteur = $this->getUser();

            $seance
                ->setRedacteur($redacteur)
                ->setObjectifs((string) $request->request->get('objectifs') ?: null)
                ->setContenu((string) $request->request->get('contenu') ?: null)
                ->setPubliee((bool) $request->request->get('publiee'))
                ->toucher();

            $entityManager->persist($seance);
            $entityManager->flush();

            $this->addFlash('success', 'Séance enregistrée.');

            return $this->redirectToRoute('app_creneau_detail', ['id' => $creneau->getId(), 'date' => $date]);
        }

        $seancePrecedente = $seance->estVide() ? $seanceRepository->findDernierePourCreneau($creneau, $dateObjet) : null;

        return $this->render('seance/form.html.twig', [
            'creneau' => $creneau,
            'date' => $dateObjet,
            'seance' => $seance,
            'seancePrecedente' => $seancePrecedente,
        ]);
    }

    #[Route('/schemas/nouveau', name: 'schema_new', methods: ['GET', 'POST'])]
    public function nouveauSchema(Request $request, Creneau $creneau, string $date, SeanceRepository $seanceRepository, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $seance = $seanceRepository->findOneByCreneauEtDate($creneau, $dateObjet);
        if (!$seance) {
            /** @var Licencie $redacteur */
            $redacteur = $this->getUser();
            $seance = (new Seance())->setCreneau($creneau)->setDate($dateObjet)->setRedacteur($redacteur);
            $entityManager->persist($seance);
            $entityManager->flush();
        }

        $schema = (new SeanceSchema())->setSeance($seance)->setOrdre(count($seance->getSchemas()));

        return $this->traiterSchema($request, $creneau, $dateObjet, $schema, $entityManager);
    }

    #[Route('/schemas/{schemaId}/modifier', name: 'schema_edit', methods: ['GET', 'POST'])]
    public function modifierSchema(Request $request, Creneau $creneau, string $date, int $schemaId, SeanceSchemaRepository $seanceSchemaRepository, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $schema = $seanceSchemaRepository->find($schemaId);
        if (!$schema || $schema->getSeance()?->getCreneau() !== $creneau) {
            throw $this->createNotFoundException();
        }

        return $this->traiterSchema($request, $creneau, $dateObjet, $schema, $entityManager);
    }

    #[Route('/schemas/{schemaId}/supprimer', name: 'schema_delete', methods: ['POST'])]
    public function supprimerSchema(Request $request, Creneau $creneau, string $date, int $schemaId, SeanceSchemaRepository $seanceSchemaRepository, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $schema = $seanceSchemaRepository->find($schemaId);
        if ($schema && $schema->getSeance()?->getCreneau() === $creneau
            && $this->isCsrfTokenValid('schema-delete-'.$schema->getId(), (string) $request->request->get('_token'))
        ) {
            $entityManager->remove($schema);
            $entityManager->flush();
            $this->addFlash('success', 'Schéma supprimé.');
        }

        return $this->redirectToRoute('app_seance_edit', ['id' => $creneau->getId(), 'date' => $date]);
    }

    private function traiterSchema(Request $request, Creneau $creneau, \DateTimeImmutable $date, SeanceSchema $schema, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $token = 'seance-schema-'.$creneau->getId().'-'.$date->format('Y-m-d').'-'.($schema->getId() ?? 'nouveau');
            if (!$this->isCsrfTokenValid($token, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_seance_edit', ['id' => $creneau->getId(), 'date' => $date->format('Y-m-d')]);
            }

            $titre = trim((string) $request->request->get('titre')) ?: null;
            $donneesRaw = (string) $request->request->get('donnees');
            json_decode($donneesRaw);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $donneesRaw = '{"terrains":1,"formes":[]}';
            }

            $schema->setTitre($titre)->setDonnees($donneesRaw);

            $entityManager->persist($schema);
            $entityManager->flush();

            $this->addFlash('success', 'Schéma enregistré.');

            return $this->redirectToRoute('app_seance_edit', ['id' => $creneau->getId(), 'date' => $date->format('Y-m-d')]);
        }

        return $this->render('seance/schema_form.html.twig', [
            'creneau' => $creneau,
            'date' => $date,
            'schema' => $schema,
        ]);
    }

    /**
     * Préremplit le formulaire (sans enregistrer) avec le contenu de la dernière séance
     * renseignée, pour aller plus vite plutôt que de tout retaper chaque semaine.
     */
    #[Route('/dupliquer', name: 'dupliquer', methods: ['GET'])]
    public function dupliquer(Creneau $creneau, string $date, SeanceRepository $seanceRepository): Response
    {
        $this->refuserSiPasEntraineur($creneau);

        $dateObjet = new \DateTimeImmutable($date);
        $modele = $seanceRepository->findDernierePourCreneau($creneau, $dateObjet);

        $seance = (new Seance())->setCreneau($creneau)->setDate($dateObjet);
        if ($modele) {
            $seance->setObjectifs($modele->getObjectifs())->setContenu($modele->getContenu());
        }

        return $this->render('seance/form.html.twig', [
            'creneau' => $creneau,
            'date' => $dateObjet,
            'seance' => $seance,
            'seancePrecedente' => null,
        ]);
    }

    private function refuserSiPasEntraineur(Creneau $creneau): void
    {
        /** @var Licencie $utilisateur */
        $utilisateur = $this->getUser();

        if (!$creneau->isEncadre() || (!$creneau->estEncadrePar($utilisateur) && !$this->isGranted('ROLE_BUREAU'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
