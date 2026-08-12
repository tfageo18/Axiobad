<?php

namespace App\Controller;

use App\Entity\DemandeCordage;
use App\Entity\Licencie;
use App\Entity\StockCordage;
use App\Entity\StockMouvementCordage;
use App\Repository\DemandeCordageRepository;
use App\Repository\RaquetteRepository;
use App\Repository\StockCordageRepository;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cordage')]
class CordageController extends AbstractController
{
    #[Route('', name: 'app_cordage_index', methods: ['GET'])]
    public function index(DemandeCordageRepository $demandeCordageRepository): Response
    {
        $peutGererToutes = $this->estBureauOuCordeur();

        $demandes = $peutGererToutes
            ? $demandeCordageRepository->findBy([], ['dateDepot' => 'DESC'])
            : $demandeCordageRepository->findBy(['licencie' => $this->getUser()], ['dateDepot' => 'DESC']);

        return $this->render('cordage/index.html.twig', [
            'demandes' => $demandes,
            'peutGererToutes' => $peutGererToutes,
        ]);
    }

    #[Route('/nouveau', name: 'app_cordage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, StockCordageRepository $stockCordageRepository, RaquetteRepository $raquetteRepository): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            $stockCordage = $this->trouverStockDisponible($request->request->get('stockCordage'), $stockCordageRepository);
            $raquetteId = $request->request->get('raquette');
            $raquette = $raquetteId ? $raquetteRepository->find($raquetteId) : null;
            if ($raquette && $raquette->getLicencie() !== $licencie) {
                $raquette = null;
            }

            $demande = (new DemandeCordage())
                ->setLicencie($licencie)
                ->setStockCordage($stockCordage)
                ->setRaquette($raquette)
                ->setTension((string) $request->request->get('tension') ?: null)
                ->setLieuDepose((string) $request->request->get('lieuDepose'));

            $entityManager->persist($demande);
            $entityManager->flush();

            $this->addFlash('success', 'Demande de cordage enregistrée.');

            return $this->redirectToRoute('app_cordage_index');
        }

        return $this->render('cordage/form.html.twig', [
            'demande' => null,
            'articlesDisponibles' => $stockCordageRepository->findDisponibles(),
            'raquettes' => $raquetteRepository->findBy(['licencie' => $licencie]),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cordage_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager, StockCordageRepository $stockCordageRepository, RaquetteRepository $raquetteRepository): Response
    {
        $this->refuserSiPasBureauNiCordeur();

        if ($request->isMethod('POST')) {
            $stockCordage = $this->trouverStockDisponible($request->request->get('stockCordage'), $stockCordageRepository, $demande->getStockCordage());
            $raquetteId = $request->request->get('raquette');
            $raquette = $raquetteId ? $raquetteRepository->find($raquetteId) : null;
            if ($raquette && $raquette->getLicencie() !== $demande->getLicencie()) {
                $raquette = null;
            }

            $demande
                ->setStockCordage($stockCordage)
                ->setRaquette($raquette)
                ->setTension((string) $request->request->get('tension') ?: null)
                ->setLieuDepose((string) $request->request->get('lieuDepose'));

            $entityManager->flush();

            $this->addFlash('success', 'Demande modifiée.');

            return $this->redirectToRoute('app_cordage_index');
        }

        // L'article déjà choisi doit rester proposé même s'il n'a plus de stock disponible
        // (sinon impossible de rouvrir le formulaire sans perdre le choix initial).
        $articlesDisponibles = $stockCordageRepository->findDisponibles();
        if ($demande->getStockCordage() && !in_array($demande->getStockCordage(), $articlesDisponibles, true)) {
            $articlesDisponibles[] = $demande->getStockCordage();
        }

        return $this->render('cordage/form.html.twig', [
            'demande' => $demande,
            'articlesDisponibles' => $articlesDisponibles,
            'raquettes' => $raquetteRepository->findBy(['licencie' => $demande->getLicencie()]),
        ]);
    }

    /**
     * Un licencié ne peut choisir que du stock réellement disponible ; un membre du bureau/cordeur
     * (via edit()) peut en plus garder l'article déjà associé à la demande même s'il n'a plus de
     * stock, pour ne pas perdre l'information en rouvrant le formulaire.
     */
    private function trouverStockDisponible(mixed $id, StockCordageRepository $stockCordageRepository, ?StockCordage $articleDejaChoisi = null): ?StockCordage
    {
        if (!$id) {
            return null;
        }

        $article = $stockCordageRepository->find($id);
        if (!$article) {
            return null;
        }

        if ($article->getQuantite() > 0 || $article === $articleDejaChoisi) {
            return $article;
        }

        return null;
    }

    #[Route('/{id}/annuler', name: 'app_cordage_delete', methods: ['POST'])]
    public function delete(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager): Response
    {
        $estProprietaire = $demande->getLicencie() === $this->getUser();
        if (!$estProprietaire && !$this->estBureauOuCordeur()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('annuler-cordage-'.$demande->getId(), (string) $request->request->get('_token'))) {
            if ($demande->getStockCordage()) {
                $quantiteARestituer = $demande->getStockCordage()->isBobine()
                    ? ($demande->getLongueurUtiliseeM() ?? StockCordage::METRES_PAR_RAQUETTE)
                    : 1;

                $mouvement = (new StockMouvementCordage())
                    ->setArticle($demande->getStockCordage())
                    ->setType(StockMouvementCordage::TYPE_ENTREE)
                    ->setQuantite($quantiteARestituer)
                    ->setMotif(sprintf('Annulation de la demande de cordage #%d', $demande->getId()))
                    ->setAuteur($this->getUser());
                $demande->getStockCordage()->ajusterQuantite($quantiteARestituer);
                $entityManager->persist($mouvement);
            }

            $entityManager->remove($demande);
            $entityManager->flush();
            $this->addFlash('success', 'Demande annulée.');
        }

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/{id}/prendre-en-charge', name: 'app_cordage_prendre_en_charge', methods: ['GET', 'POST'])]
    public function prendreEnCharge(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager, StockCordageRepository $stockCordageRepository): Response
    {
        $this->refuserSiPasBureauNiCordeur();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('prendre-en-charge-'.$demande->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_cordage_prendre_en_charge', ['id' => $demande->getId()]);
            }

            $stockCordage = $stockCordageRepository->find($request->request->get('stockCordage'));
            $longueurUtiliseeM = (int) $request->request->get('longueurUtiliseeM');

            if ($stockCordage) {
                $quantiteConsommee = $stockCordage->isBobine() ? max(1, $longueurUtiliseeM ?: StockCordage::METRES_PAR_RAQUETTE) : 1;

                if ($quantiteConsommee > $stockCordage->getQuantite()) {
                    $this->addFlash('error', sprintf('Stock insuffisant sur cet article (%d %s en stock).', $stockCordage->getQuantite(), $stockCordage->getUniteLabel()));

                    return $this->redirectToRoute('app_cordage_prendre_en_charge', ['id' => $demande->getId()]);
                }

                $mouvement = (new StockMouvementCordage())
                    ->setArticle($stockCordage)
                    ->setType(StockMouvementCordage::TYPE_SORTIE)
                    ->setQuantite($quantiteConsommee)
                    ->setMotif(sprintf('Demande de cordage #%d — %s', $demande->getId(), $demande->getLicencie()->getNomComplet()))
                    ->setAuteur($this->getUser());
                $stockCordage->ajusterQuantite(-$quantiteConsommee);
                $entityManager->persist($mouvement);

                $demande->setStockCordage($stockCordage);
                $demande->setLongueurUtiliseeM($stockCordage->isBobine() ? $quantiteConsommee : null);
            }

            $demande->setStatut(DemandeCordage::STATUT_EN_COURS);
            $demande->setCordeur($this->getUser());
            $entityManager->flush();

            $this->addFlash('success', 'Raquette prise en charge.');

            return $this->redirectToRoute('app_cordage_index');
        }

        return $this->render('cordage/prendre_en_charge.html.twig', [
            'demande' => $demande,
            'articles' => $stockCordageRepository->findAll(),
        ]);
    }

    #[Route('/{id}/marquer-prete', name: 'app_cordage_marquer_prete', methods: ['POST'])]
    public function marquerPrete(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager, NotificationMailer $notificationMailer): Response
    {
        $this->refuserSiPasBureauNiCordeur();

        $prix = $request->request->get('prix');

        $demande
            ->setStatut(DemandeCordage::STATUT_PRETE)
            ->setLieuRetour((string) $request->request->get('lieuRetour') ?: null)
            ->setPrix(null !== $prix && '' !== $prix ? (float) $prix : null)
            ->setDatePrete(new \DateTimeImmutable());

        if (!$demande->getCordeur()) {
            $demande->setCordeur($this->getUser());
        }

        $entityManager->flush();
        $notificationMailer->cordagePret($demande);

        $this->addFlash('success', 'Raquette marquée comme prête.');

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/{id}/marquer-recuperee', name: 'app_cordage_marquer_recuperee', methods: ['POST'])]
    public function marquerRecuperee(DemandeCordage $demande, EntityManagerInterface $entityManager): Response
    {
        $estProprietaire = $demande->getLicencie() === $this->getUser();
        if (!$estProprietaire && !$this->estBureauOuCordeur()) {
            throw $this->createAccessDeniedException();
        }

        $demande
            ->setStatut(DemandeCordage::STATUT_RECUPEREE)
            ->setDateRecuperee(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'Raquette marquée comme récupérée.');

        return $this->redirectToRoute('app_cordage_index');
    }

    private function estBureauOuCordeur(): bool
    {
        return $this->isGranted('ROLE_BUREAU') || $this->isGranted('ROLE_CORDEUR');
    }

    private function refuserSiPasBureauNiCordeur(): void
    {
        if (!$this->estBureauOuCordeur()) {
            throw $this->createAccessDeniedException();
        }
    }
}
