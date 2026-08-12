<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Entity\StockCordage;
use App\Entity\StockMouvementCordage;
use App\Repository\StockCordageRepository;
use App\Repository\StockMouvementCordageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion du stock de cordages (bobines et sachets individuels), accessible aux cordeurs (pas
 * seulement au rôle Stock/Bureau) puisque ce sont eux qui consomment ce stock au quotidien.
 */
#[Route('/cordage/stock')]
class StockCordageController extends AbstractController
{
    #[Route('', name: 'app_stock_cordage_index', methods: ['GET'])]
    public function index(StockCordageRepository $stockCordageRepository): Response
    {
        $this->refuserSiPasAutorise();

        return $this->render('stock/cordage_index.html.twig', [
            'articles' => $stockCordageRepository->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'app_stock_cordage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasAutorise();

        return $this->formulaire($request, $entityManager, new StockCordage());
    }

    #[Route('/{id}/modifier', name: 'app_stock_cordage_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StockCordage $article, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasAutorise();

        return $this->formulaire($request, $entityManager, $article);
    }

    #[Route('/{id}/supprimer', name: 'app_stock_cordage_delete', methods: ['POST'])]
    public function delete(Request $request, StockCordage $article, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasAutorise();

        if ($this->isCsrfTokenValid('delete-stock-cordage-'.$article->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($article);
            $entityManager->flush();
            $this->addFlash('success', 'Article supprimé du stock.');
        }

        return $this->redirectToRoute('app_stock_cordage_index');
    }

    #[Route('/{id}/mouvement', name: 'app_stock_cordage_mouvement', methods: ['POST'])]
    public function mouvement(Request $request, StockCordage $article, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasAutorise();

        /** @var Licencie $auteur */
        $auteur = $this->getUser();
        $type = (string) $request->request->get('type');
        $quantite = max(1, (int) $request->request->get('quantite'));

        if (!in_array($type, [StockMouvementCordage::TYPE_ENTREE, StockMouvementCordage::TYPE_SORTIE], true)) {
            $this->addFlash('error', 'Type de mouvement invalide.');

            return $this->redirectToRoute('app_stock_cordage_index');
        }

        if (StockMouvementCordage::TYPE_SORTIE === $type && $quantite > $article->getQuantite()) {
            $this->addFlash('error', sprintf('Stock insuffisant (%d %s en stock).', $article->getQuantite(), $article->getUniteLabel()));

            return $this->redirectToRoute('app_stock_cordage_index');
        }

        $mouvement = (new StockMouvementCordage())
            ->setArticle($article)
            ->setType($type)
            ->setQuantite($quantite)
            ->setMotif((string) $request->request->get('motif') ?: null)
            ->setAuteur($auteur);

        $article->ajusterQuantite(StockMouvementCordage::TYPE_ENTREE === $type ? $quantite : -$quantite);

        $entityManager->persist($mouvement);
        $entityManager->flush();

        $this->addFlash('success', 'Mouvement de stock enregistré.');

        return $this->redirectToRoute('app_stock_cordage_index');
    }

    #[Route('/{id}/historique', name: 'app_stock_cordage_historique', methods: ['GET'])]
    public function historique(StockCordage $article, StockMouvementCordageRepository $mouvementRepository): Response
    {
        $this->refuserSiPasAutorise();

        return $this->render('stock/historique.html.twig', [
            'titre' => sprintf('%s — %s', $article->getTypeLabel(), $article->getLibelle()),
            'mouvements' => $mouvementRepository->findPourArticle($article),
            'retour' => $this->generateUrl('app_stock_cordage_index'),
        ]);
    }

    private function formulaire(Request $request, EntityManagerInterface $entityManager, StockCordage $article): Response
    {
        if ($request->isMethod('POST')) {
            $prixUnitaire = $request->request->get('prixUnitaire');
            $seuilAlerte = $request->request->get('seuilAlerte');

            $article
                ->setType((string) $request->request->get('type'))
                ->setMarque((string) $request->request->get('marque') ?: null)
                ->setModele((string) $request->request->get('modele') ?: null)
                ->setCommentaire((string) $request->request->get('commentaire') ?: null)
                ->setPrixUnitaire(null !== $prixUnitaire && '' !== $prixUnitaire ? (float) $prixUnitaire : null)
                ->setSeuilAlerte(null !== $seuilAlerte && '' !== $seuilAlerte ? (int) $seuilAlerte : null)
                ->setLieuStockage((string) $request->request->get('lieuStockage') ?: null);

            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', 'Article enregistré.');

            return $this->redirectToRoute('app_stock_cordage_index');
        }

        return $this->render('stock/cordage_form.html.twig', [
            'article' => $article->getId() ? $article : null,
        ]);
    }

    private function refuserSiPasAutorise(): void
    {
        if (!$this->isGranted('ROLE_BUREAU') && !$this->isGranted('ROLE_CORDEUR') && !$this->isGranted('ROLE_STOCK')) {
            throw $this->createAccessDeniedException();
        }
    }
}
