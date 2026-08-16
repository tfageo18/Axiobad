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

            $schema->setTitre($titre)->setDonnees(self::validerEtNormaliserDonnees($donneesRaw));

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

    /**
     * Valide et renormalise strictement les données d'un schéma envoyées par le client : jamais
     * la chaîne brute soumise n'est stockée telle quelle, uniquement une structure reconstruite
     * champ par champ avec des types et bornes connus, puis ré-encodée en JSON. Toute forme mal
     * formée est simplement ignorée plutôt que de faire échouer tout l'enregistrement.
     */
    private static function validerEtNormaliserDonnees(string $brut): string
    {
        $decode = json_decode($brut, true);
        $defaut = ['terrains' => 1, 'formes' => []];
        if (!is_array($decode)) {
            return json_encode($defaut, JSON_THROW_ON_ERROR);
        }

        $terrains = (isset($decode['terrains']) && 2 === (int) $decode['terrains']) ? 2 : 1;

        $formesBrutes = is_array($decode['formes'] ?? null) ? $decode['formes'] : [];
        $formes = [];
        foreach (array_slice($formesBrutes, 0, 200) as $forme) {
            $formeValidee = self::validerForme($forme);
            if (null !== $formeValidee) {
                $formes[] = $formeValidee;
            }
        }

        return json_encode(['terrains' => $terrains, 'formes' => $formes], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null null si la forme est invalide (type inconnu ou champs
     *                                    incohérents) — elle est alors simplement écartée.
     */
    private static function validerForme(mixed $forme): ?array
    {
        if (!is_array($forme) || !isset($forme['type']) || !is_string($forme['type'])) {
            return null;
        }

        $couleur = self::validerCouleur($forme['couleur'] ?? null);
        $fraction = static fn (mixed $v): float => max(0.0, min(1.0, is_numeric($v) ? (float) $v : 0.0));

        return match ($forme['type']) {
            'trait' => self::validerTrait($forme, $couleur, $fraction),
            'fleche' => self::validerFleche($forme, $couleur, $fraction),
            'joueur' => is_numeric($forme['x'] ?? null) && is_numeric($forme['y'] ?? null) ? [
                'type' => 'joueur',
                'couleur' => $couleur,
                'x' => $fraction($forme['x']),
                'y' => $fraction($forme['y']),
                'label' => mb_substr(is_string($forme['label'] ?? null) ? $forme['label'] : '', 0, 4),
            ] : null,
            'texte' => is_numeric($forme['x'] ?? null) && is_numeric($forme['y'] ?? null) && is_string($forme['texte'] ?? null) && '' !== $forme['texte'] ? [
                'type' => 'texte',
                'couleur' => $couleur,
                'x' => $fraction($forme['x']),
                'y' => $fraction($forme['y']),
                'texte' => mb_substr($forme['texte'], 0, 300),
            ] : null,
            default => null,
        };
    }

    /**
     * @param callable(mixed): float $fraction
     */
    private static function validerTrait(array $forme, string $couleur, callable $fraction): ?array
    {
        if (!is_array($forme['points'] ?? null)) {
            return null;
        }

        $points = [];
        foreach (array_slice($forme['points'], 0, 2000) as $point) {
            if (is_array($point) && 2 === count($point) && is_numeric($point[0] ?? null) && is_numeric($point[1] ?? null)) {
                $points[] = [$fraction($point[0]), $fraction($point[1])];
            }
        }

        return count($points) >= 2 ? ['type' => 'trait', 'couleur' => $couleur, 'points' => $points] : null;
    }

    /**
     * @param callable(mixed): float $fraction
     */
    private static function validerFleche(array $forme, string $couleur, callable $fraction): ?array
    {
        $de = $forme['de'] ?? null;
        $vers = $forme['vers'] ?? null;
        if (!is_array($de) || !is_array($vers) || 2 !== count($de) || 2 !== count($vers)
            || !is_numeric($de[0] ?? null) || !is_numeric($de[1] ?? null)
            || !is_numeric($vers[0] ?? null) || !is_numeric($vers[1] ?? null)
        ) {
            return null;
        }

        return [
            'type' => 'fleche',
            'couleur' => $couleur,
            'de' => [$fraction($de[0]), $fraction($de[1])],
            'vers' => [$fraction($vers[0]), $fraction($vers[1])],
        ];
    }

    private static function validerCouleur(mixed $couleur): string
    {
        if (is_string($couleur) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $couleur)) {
            return $couleur;
        }

        return '#22c55e';
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
