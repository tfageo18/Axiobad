<?php

namespace App\Controller;

use App\Entity\Salarie;
use App\Repository\SalarieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion RH simple des salariés du club (entraîneur salarié, secrétariat, entretien...),
 * distincts des licenciés bénévoles. Pas de volet paie/comptabilité.
 */
#[Route('/salaries')]
#[IsGranted('ROLE_BUREAU')]
class SalarieController extends AbstractController
{
    #[Route('', name: 'app_salarie_index', methods: ['GET'])]
    public function index(SalarieRepository $salarieRepository): Response
    {
        return $this->render('salarie/index.html.twig', [
            'salaries' => $salarieRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_salarie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $salarie = new Salarie();
            $this->hydrater($salarie, $request);

            $entityManager->persist($salarie);
            $entityManager->flush();

            $this->addFlash('success', 'Salarié créé.');

            return $this->redirectToRoute('app_salarie_index');
        }

        return $this->render('salarie/form.html.twig', [
            'salarie' => null,
            'typesContrat' => Salarie::CONTRATS_LABELS,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_salarie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Salarie $salarie, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $this->hydrater($salarie, $request);
            $entityManager->flush();

            $this->addFlash('success', 'Salarié modifié.');

            return $this->redirectToRoute('app_salarie_index');
        }

        return $this->render('salarie/form.html.twig', [
            'salarie' => $salarie,
            'typesContrat' => Salarie::CONTRATS_LABELS,
        ]);
    }

    #[Route('/{id}/activer', name: 'app_salarie_toggle_actif', methods: ['POST'])]
    public function toggleActif(Salarie $salarie, EntityManagerInterface $entityManager): Response
    {
        $salarie->setActif(!$salarie->isActif());
        $entityManager->flush();

        $this->addFlash('success', $salarie->isActif() ? 'Salarié réactivé.' : 'Salarié désactivé.');

        return $this->redirectToRoute('app_salarie_index');
    }

    #[Route('/{id}/supprimer', name: 'app_salarie_delete', methods: ['POST'])]
    public function delete(Request $request, Salarie $salarie, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-salarie-'.$salarie->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($salarie);
            $entityManager->flush();
            $this->addFlash('success', 'Salarié supprimé.');
        }

        return $this->redirectToRoute('app_salarie_index');
    }

    private function hydrater(Salarie $salarie, Request $request): void
    {
        $typeContrat = (string) $request->request->get('typeContrat');
        $dateDebut = (string) $request->request->get('dateDebut');
        $dateFin = (string) $request->request->get('dateFin');

        $salarie
            ->setPrenom((string) $request->request->get('prenom'))
            ->setNom((string) $request->request->get('nom'))
            ->setPoste((string) $request->request->get('poste'))
            ->setTypeContrat(array_key_exists($typeContrat, Salarie::CONTRATS_LABELS) ? $typeContrat : Salarie::CONTRAT_AUTRE)
            ->setDateDebut($dateDebut ? new \DateTimeImmutable($dateDebut) : null)
            ->setDateFin($dateFin ? new \DateTimeImmutable($dateFin) : null)
            ->setTelephone((string) $request->request->get('telephone') ?: null)
            ->setEmail((string) $request->request->get('email') ?: null)
            ->setNotes((string) $request->request->get('notes') ?: null);
    }
}
