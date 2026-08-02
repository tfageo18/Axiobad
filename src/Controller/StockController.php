<?php

namespace App\Controller;

use App\Entity\StockVetement;
use App\Entity\StockVolant;
use App\Repository\StockVetementRepository;
use App\Repository\StockVolantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock')]
#[IsGranted('ROLE_BUREAU')]
class StockController extends AbstractController
{
    #[Route('', name: 'app_stock_index', methods: ['GET'])]
    public function index(StockVetementRepository $vetementRepository, StockVolantRepository $volantRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'vetements' => $vetementRepository->findAll(),
            'volants' => $volantRepository->findAll(),
        ]);
    }

    #[Route('/vetements/nouveau', name: 'app_stock_vetement_new', methods: ['GET', 'POST'])]
    public function newVetement(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaireVetement($request, $entityManager, new StockVetement());
    }

    #[Route('/vetements/{id}/modifier', name: 'app_stock_vetement_edit', methods: ['GET', 'POST'])]
    public function editVetement(Request $request, StockVetement $vetement, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaireVetement($request, $entityManager, $vetement);
    }

    #[Route('/vetements/{id}/supprimer', name: 'app_stock_vetement_delete', methods: ['POST'])]
    public function deleteVetement(Request $request, StockVetement $vetement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-stock-vetement-'.$vetement->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($vetement);
            $entityManager->flush();
            $this->addFlash('success', 'Article supprimé du stock.');
        }

        return $this->redirectToRoute('app_stock_index');
    }

    #[Route('/volants/nouveau', name: 'app_stock_volant_new', methods: ['GET', 'POST'])]
    public function newVolant(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaireVolant($request, $entityManager, new StockVolant());
    }

    #[Route('/volants/{id}/modifier', name: 'app_stock_volant_edit', methods: ['GET', 'POST'])]
    public function editVolant(Request $request, StockVolant $volant, EntityManagerInterface $entityManager): Response
    {
        return $this->formulaireVolant($request, $entityManager, $volant);
    }

    #[Route('/volants/{id}/supprimer', name: 'app_stock_volant_delete', methods: ['POST'])]
    public function deleteVolant(Request $request, StockVolant $volant, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-stock-volant-'.$volant->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($volant);
            $entityManager->flush();
            $this->addFlash('success', 'Tube de volants supprimé du stock.');
        }

        return $this->redirectToRoute('app_stock_index');
    }

    private function formulaireVetement(Request $request, EntityManagerInterface $entityManager, StockVetement $vetement): Response
    {
        if ($request->isMethod('POST')) {
            $vetement
                ->setType((string) $request->request->get('type'))
                ->setTaille((string) $request->request->get('taille'))
                ->setQuantite((int) $request->request->get('quantite'))
                ->setMarque((string) $request->request->get('marque') ?: null)
                ->setCommentaire((string) $request->request->get('commentaire') ?: null);

            $entityManager->persist($vetement);
            $entityManager->flush();

            $this->addFlash('success', 'Stock de vêtements enregistré.');

            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/vetement_form.html.twig', [
            'vetement' => $vetement->getId() ? $vetement : null,
        ]);
    }

    private function formulaireVolant(Request $request, EntityManagerInterface $entityManager, StockVolant $volant): Response
    {
        if ($request->isMethod('POST')) {
            $volant
                ->setType((string) $request->request->get('type'))
                ->setVitesse((string) $request->request->get('vitesse'))
                ->setDestination((string) $request->request->get('destination'))
                ->setQuantiteTubes((int) $request->request->get('quantiteTubes'))
                ->setMarque((string) $request->request->get('marque') ?: null)
                ->setCommentaire((string) $request->request->get('commentaire') ?: null);

            $entityManager->persist($volant);
            $entityManager->flush();

            $this->addFlash('success', 'Stock de volants enregistré.');

            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/volant_form.html.twig', [
            'volant' => $volant->getId() ? $volant : null,
        ]);
    }
}
