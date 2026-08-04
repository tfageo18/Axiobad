<?php

namespace App\Controller;

use App\Entity\InventaireCampagne;
use App\Entity\InventaireLigne;
use App\Entity\Licencie;
use App\Entity\StockMouvementVetement;
use App\Entity\StockMouvementVolant;
use App\Repository\InventaireCampagneRepository;
use App\Repository\StockVetementRepository;
use App\Repository\StockVolantRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock/inventaire')]
#[IsGranted('ROLE_STOCK')]
class InventaireController extends AbstractController
{
    #[Route('', name: 'app_inventaire_index', methods: ['GET'])]
    public function index(InventaireCampagneRepository $campagneRepository): Response
    {
        return $this->render('inventaire/index.html.twig', [
            'campagnes' => $campagneRepository->findRecentes(),
        ]);
    }

    #[Route('/nouveau', name: 'app_inventaire_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        StockVetementRepository $vetementRepository,
        StockVolantRepository $volantRepository,
    ): Response {
        /** @var Licencie $auteur */
        $auteur = $this->getUser();

        $campagne = (new InventaireCampagne())
            ->setNom((string) $request->request->get('nom') ?: sprintf('Inventaire du %s', (new \DateTimeImmutable())->format('d/m/Y')))
            ->setAuteur($auteur);

        $entityManager->persist($campagne);

        foreach ($vetementRepository->findAll() as $vetement) {
            $ligne = (new InventaireLigne())
                ->setCampagne($campagne)
                ->setVetement($vetement)
                ->setLibelleArticle(sprintf('%s — %s%s', $vetement->getTypeLabel(), $vetement->getTaille(), $vetement->getMarque() ? ' ('.$vetement->getMarque().')' : ''))
                ->setQuantiteTheorique($vetement->getQuantite());
            $entityManager->persist($ligne);
        }

        foreach ($volantRepository->findAll() as $volant) {
            $ligne = (new InventaireLigne())
                ->setCampagne($campagne)
                ->setVolant($volant)
                ->setLibelleArticle(sprintf('%s — vitesse %s%s', $volant->getTypeLabel(), $volant->getVitesse(), $volant->getMarque() ? ' ('.$volant->getMarque().')' : ''))
                ->setQuantiteTheorique($volant->getQuantiteTubes());
            $entityManager->persist($ligne);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Campagne d\'inventaire créée. Saisissez les quantités comptées.');

        return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
    }

    #[Route('/{id}', name: 'app_inventaire_detail', methods: ['GET'])]
    public function detail(InventaireCampagne $campagne): Response
    {
        return $this->render('inventaire/detail.html.twig', [
            'campagne' => $campagne,
        ]);
    }

    #[Route('/{id}/lignes/{ligneId}', name: 'app_inventaire_ligne_saisir', methods: ['POST'])]
    public function saisirLigne(Request $request, InventaireCampagne $campagne, int $ligneId, EntityManagerInterface $entityManager): Response
    {
        if ($campagne->estValidee()) {
            $this->addFlash('error', 'Cette campagne est déjà validée, elle ne peut plus être modifiée.');

            return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
        }

        foreach ($campagne->getLignes() as $ligne) {
            if ($ligne->getId() === $ligneId) {
                $quantite = $request->request->get('quantiteComptee');
                $ligne
                    ->setQuantiteComptee(null !== $quantite && '' !== $quantite ? (int) $quantite : null)
                    ->setMotif((string) $request->request->get('motif') ?: null);
                $entityManager->flush();

                break;
            }
        }

        return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
    }

    #[Route('/{id}/valider', name: 'app_inventaire_valider', methods: ['POST'])]
    public function valider(Request $request, InventaireCampagne $campagne, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('valider-inventaire-'.$campagne->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
        }

        if ($campagne->estValidee()) {
            return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
        }

        /** @var Licencie $auteur */
        $auteur = $this->getUser();
        $regularisations = 0;

        foreach ($campagne->getLignes() as $ligne) {
            if (!$ligne->aUnEcart()) {
                continue;
            }

            $ecart = $ligne->getEcart();
            $motif = sprintf('Régularisation inventaire « %s »%s', $campagne->getNom(), $ligne->getMotif() ? ' — '.$ligne->getMotif() : '');

            if ($ligne->getVetement()) {
                $mouvement = (new StockMouvementVetement())
                    ->setArticle($ligne->getVetement())
                    ->setType($ecart > 0 ? StockMouvementVetement::TYPE_ENTREE : StockMouvementVetement::TYPE_SORTIE)
                    ->setQuantite(abs($ecart))
                    ->setMotif($motif)
                    ->setAuteur($auteur);
                $ligne->getVetement()->ajusterQuantite($ecart);
                $entityManager->persist($mouvement);
            } elseif ($ligne->getVolant()) {
                $mouvement = (new StockMouvementVolant())
                    ->setArticle($ligne->getVolant())
                    ->setType($ecart > 0 ? StockMouvementVolant::TYPE_ENTREE : StockMouvementVolant::TYPE_SORTIE)
                    ->setQuantite(abs($ecart))
                    ->setMotif($motif)
                    ->setAuteur($auteur);
                $ligne->getVolant()->ajusterQuantite($ecart);
                $entityManager->persist($mouvement);
            }

            $auditLogger->log(
                AuditLogger::STOCK_CORRECTION,
                'InventaireLigne',
                $ligne->getLibelleArticle(),
                (string) $ligne->getQuantiteTheorique(),
                sprintf('%d (écart %+d)', $ligne->getQuantiteComptee(), $ecart)
            );

            ++$regularisations;
        }

        $campagne->setStatut(InventaireCampagne::STATUT_VALIDEE)->setValideeLe(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', sprintf('Inventaire validé : %d régularisation(s) appliquée(s) au stock.', $regularisations));

        return $this->redirectToRoute('app_inventaire_detail', ['id' => $campagne->getId()]);
    }

    #[Route('/{id}/export.csv', name: 'app_inventaire_export', methods: ['GET'])]
    public function export(InventaireCampagne $campagne): Response
    {
        $lignes = ["Article;Quantité théorique;Quantité comptée;Écart;Motif"];
        foreach ($campagne->getLignes() as $ligne) {
            $lignes[] = implode(';', [
                str_replace(';', ',', $ligne->getLibelleArticle()),
                $ligne->getQuantiteTheorique(),
                $ligne->getQuantiteComptee() ?? '',
                $ligne->getEcart() ?? '',
                str_replace(';', ',', $ligne->getMotif() ?? ''),
            ]);
        }

        $response = new Response(implode("\n", $lignes));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="inventaire-%d.csv"', $campagne->getId()));

        return $response;
    }
}
