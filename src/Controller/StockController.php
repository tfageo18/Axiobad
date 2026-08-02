<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Entity\StockMouvementVetement;
use App\Entity\StockMouvementVolant;
use App\Entity\StockVetement;
use App\Entity\StockVolant;
use App\Repository\StockMouvementVetementRepository;
use App\Repository\StockMouvementVolantRepository;
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

    #[Route('/vetements/{id}/mouvement', name: 'app_stock_vetement_mouvement', methods: ['POST'])]
    public function mouvementVetement(Request $request, StockVetement $vetement, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $auteur */
        $auteur = $this->getUser();
        $type = (string) $request->request->get('type');
        $quantite = max(1, (int) $request->request->get('quantite'));

        if (!in_array($type, [StockMouvementVetement::TYPE_ENTREE, StockMouvementVetement::TYPE_SORTIE], true)) {
            $this->addFlash('error', 'Type de mouvement invalide.');

            return $this->redirectToRoute('app_stock_index');
        }

        if (StockMouvementVetement::TYPE_SORTIE === $type && $quantite > $vetement->getQuantite()) {
            $this->addFlash('error', sprintf('Stock insuffisant (%d en stock).', $vetement->getQuantite()));

            return $this->redirectToRoute('app_stock_index');
        }

        $mouvement = (new StockMouvementVetement())
            ->setArticle($vetement)
            ->setType($type)
            ->setQuantite($quantite)
            ->setMotif((string) $request->request->get('motif') ?: null)
            ->setAuteur($auteur);

        $vetement->ajusterQuantite(StockMouvementVetement::TYPE_ENTREE === $type ? $quantite : -$quantite);

        $entityManager->persist($mouvement);
        $entityManager->flush();

        $this->addFlash('success', 'Mouvement de stock enregistré.');

        return $this->redirectToRoute('app_stock_index');
    }

    #[Route('/vetements/{id}/historique', name: 'app_stock_vetement_historique', methods: ['GET'])]
    public function historiqueVetement(StockVetement $vetement, StockMouvementVetementRepository $mouvementRepository): Response
    {
        return $this->render('stock/historique.html.twig', [
            'titre' => sprintf('%s — %s', $vetement->getTypeLabel(), $vetement->getTaille()),
            'mouvements' => $mouvementRepository->findPourArticle($vetement),
            'retour' => $this->generateUrl('app_stock_index'),
        ]);
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

    #[Route('/volants/{id}/mouvement', name: 'app_stock_volant_mouvement', methods: ['POST'])]
    public function mouvementVolant(Request $request, StockVolant $volant, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $auteur */
        $auteur = $this->getUser();
        $type = (string) $request->request->get('type');
        $quantite = max(1, (int) $request->request->get('quantite'));

        if (!in_array($type, [StockMouvementVolant::TYPE_ENTREE, StockMouvementVolant::TYPE_SORTIE], true)) {
            $this->addFlash('error', 'Type de mouvement invalide.');

            return $this->redirectToRoute('app_stock_index');
        }

        if (StockMouvementVolant::TYPE_SORTIE === $type && $quantite > $volant->getQuantiteTubes()) {
            $this->addFlash('error', sprintf('Stock insuffisant (%d tube(s) en stock).', $volant->getQuantiteTubes()));

            return $this->redirectToRoute('app_stock_index');
        }

        $mouvement = (new StockMouvementVolant())
            ->setArticle($volant)
            ->setType($type)
            ->setQuantite($quantite)
            ->setMotif((string) $request->request->get('motif') ?: null)
            ->setAuteur($auteur);

        $volant->ajusterQuantite(StockMouvementVolant::TYPE_ENTREE === $type ? $quantite : -$quantite);

        $entityManager->persist($mouvement);
        $entityManager->flush();

        $this->addFlash('success', 'Mouvement de stock enregistré.');

        return $this->redirectToRoute('app_stock_index');
    }

    #[Route('/volants/{id}/historique', name: 'app_stock_volant_historique', methods: ['GET'])]
    public function historiqueVolant(StockVolant $volant, StockMouvementVolantRepository $mouvementRepository): Response
    {
        return $this->render('stock/historique.html.twig', [
            'titre' => sprintf('%s — vitesse %s', $volant->getTypeLabel(), $volant->getVitesse()),
            'mouvements' => $mouvementRepository->findPourArticle($volant),
            'retour' => $this->generateUrl('app_stock_index'),
        ]);
    }

    private function formulaireVetement(Request $request, EntityManagerInterface $entityManager, StockVetement $vetement): Response
    {
        if ($request->isMethod('POST')) {
            $prixUnitaire = $request->request->get('prixUnitaire');

            $vetement
                ->setType((string) $request->request->get('type'))
                ->setTaille((string) $request->request->get('taille'))
                ->setMarque((string) $request->request->get('marque') ?: null)
                ->setCommentaire((string) $request->request->get('commentaire') ?: null)
                ->setPrixUnitaire(null !== $prixUnitaire && '' !== $prixUnitaire ? (float) $prixUnitaire : null);

            $entityManager->persist($vetement);
            $entityManager->flush();

            $this->addFlash('success', 'Article enregistré.');

            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/vetement_form.html.twig', [
            'vetement' => $vetement->getId() ? $vetement : null,
        ]);
    }

    private function formulaireVolant(Request $request, EntityManagerInterface $entityManager, StockVolant $volant): Response
    {
        if ($request->isMethod('POST')) {
            $prixUnitaire = $request->request->get('prixUnitaire');

            $volant
                ->setType((string) $request->request->get('type'))
                ->setVitesse((string) $request->request->get('vitesse'))
                ->setDestination((string) $request->request->get('destination'))
                ->setMarque((string) $request->request->get('marque') ?: null)
                ->setModele((string) $request->request->get('modele') ?: null)
                ->setCommentaire((string) $request->request->get('commentaire') ?: null)
                ->setPrixUnitaire(null !== $prixUnitaire && '' !== $prixUnitaire ? (float) $prixUnitaire : null);

            $entityManager->persist($volant);
            $entityManager->flush();

            $this->addFlash('success', 'Article enregistré.');

            return $this->redirectToRoute('app_stock_index');
        }

        return $this->render('stock/volant_form.html.twig', [
            'volant' => $volant->getId() ? $volant : null,
        ]);
    }
}
