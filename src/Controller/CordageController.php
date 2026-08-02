<?php

namespace App\Controller;

use App\Entity\DemandeCordage;
use App\Entity\Licencie;
use App\Entity\TypeCordage;
use App\Repository\DemandeCordageRepository;
use App\Repository\TypeCordageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function new(Request $request, EntityManagerInterface $entityManager, TypeCordageRepository $typeCordageRepository): Response
    {
        if ($request->isMethod('POST')) {
            $typeCordage = $typeCordageRepository->find($request->request->get('typeCordage'));

            $demande = (new DemandeCordage())
                ->setLicencie($this->getUser())
                ->setTypeCordage($typeCordage)
                ->setTension((string) $request->request->get('tension') ?: null)
                ->setLieuDepose((string) $request->request->get('lieuDepose'));

            $entityManager->persist($demande);
            $entityManager->flush();

            $this->addFlash('success', 'Demande de cordage enregistrée.');

            return $this->redirectToRoute('app_cordage_index');
        }

        return $this->render('cordage/form.html.twig', [
            'typesCordage' => $typeCordageRepository->findBy(['actif' => true]),
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_cordage_delete', methods: ['POST'])]
    public function delete(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager): Response
    {
        $estProprietaire = $demande->getLicencie() === $this->getUser();
        if (!$estProprietaire && !$this->isGranted('ROLE_BUREAU')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('annuler-cordage-'.$demande->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($demande);
            $entityManager->flush();
            $this->addFlash('success', 'Demande annulée.');
        }

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/{id}/prendre-en-charge', name: 'app_cordage_prendre_en_charge', methods: ['POST'])]
    public function prendreEnCharge(DemandeCordage $demande, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasBureauNiCordeur();

        $demande->setStatut(DemandeCordage::STATUT_EN_COURS);
        $demande->setCordeur($this->getUser());
        $entityManager->flush();

        $this->addFlash('success', 'Raquette prise en charge.');

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/{id}/marquer-prete', name: 'app_cordage_marquer_prete', methods: ['POST'])]
    public function marquerPrete(Request $request, DemandeCordage $demande, EntityManagerInterface $entityManager): Response
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

        $this->addFlash('success', 'Raquette marquée comme prête.');

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/{id}/marquer-recuperee', name: 'app_cordage_marquer_recuperee', methods: ['POST'])]
    public function marquerRecuperee(DemandeCordage $demande, EntityManagerInterface $entityManager): Response
    {
        $this->refuserSiPasBureauNiCordeur();

        $demande
            ->setStatut(DemandeCordage::STATUT_RECUPEREE)
            ->setDateRecuperee(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'Raquette marquée comme récupérée.');

        return $this->redirectToRoute('app_cordage_index');
    }

    #[Route('/types', name: 'app_cordage_type_index', methods: ['GET'])]
    #[IsGranted('ROLE_BUREAU')]
    public function types(TypeCordageRepository $typeCordageRepository): Response
    {
        return $this->render('cordage/types.html.twig', [
            'types' => $typeCordageRepository->findAll(),
        ]);
    }

    #[Route('/types/nouveau', name: 'app_cordage_type_new', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function ajouterType(Request $request, EntityManagerInterface $entityManager): Response
    {
        $nom = (string) $request->request->get('nom');
        if ($nom) {
            $type = (new TypeCordage())->setNom($nom);
            $entityManager->persist($type);
            $entityManager->flush();
            $this->addFlash('success', 'Cordage ajouté au catalogue.');
        }

        return $this->redirectToRoute('app_cordage_type_index');
    }

    #[Route('/types/{id}/activer', name: 'app_cordage_type_toggle_actif', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function toggleActifType(TypeCordage $type, EntityManagerInterface $entityManager): Response
    {
        $type->setActif(!$type->isActif());
        $entityManager->flush();

        return $this->redirectToRoute('app_cordage_type_index');
    }

    #[Route('/types/{id}/supprimer', name: 'app_cordage_type_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUREAU')]
    public function supprimerType(Request $request, TypeCordage $type, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-type-cordage-'.$type->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($type);
            $entityManager->flush();
            $this->addFlash('success', 'Cordage supprimé du catalogue.');
        }

        return $this->redirectToRoute('app_cordage_type_index');
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
