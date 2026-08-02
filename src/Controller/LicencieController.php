<?php

namespace App\Controller;

use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\SaisonRepository;
use App\Service\FfbadClassementService;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/licencies')]
#[IsGranted('ROLE_BUREAU')]
class LicencieController extends AbstractController
{
    #[Route('', name: 'app_licencie_index', methods: ['GET'])]
    public function index(
        Request $request,
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        AdhesionRepository $adhesionRepository,
    ): Response {
        $saisonId = $request->query->get('saison');
        $saison = $saisonId ? $saisonRepository->find($saisonId) : $saisonRepository->findEnCours();

        return $this->render('licencie/index.html.twig', [
            'licencies' => $licencieRepository->findAll(),
            'saisons' => $saisonRepository->findAllTrieesParDate(),
            'saison' => $saison,
            'adhesions' => $saison ? $adhesionRepository->findParLicenciePourSaison($saison) : [],
        ]);
    }

    #[Route('/{id}/adhesion', name: 'app_licencie_adhesion', methods: ['POST'])]
    public function adhesion(Request $request, Licencie $licencie, EntityManagerInterface $entityManager, SaisonRepository $saisonRepository, AdhesionRepository $adhesionRepository): Response
    {
        $saison = $saisonRepository->find($request->request->get('saison'));
        if (!$saison) {
            $this->addFlash('error', 'Saison invalide.');

            return $this->redirectToRoute('app_licencie_index');
        }

        $adhesion = $adhesionRepository->findOneByLicencieEtSaison($licencie, $saison)
            ?? (new Adhesion())->setLicencie($licencie)->setSaison($saison);

        $adhesion->setPayee('1' === $request->request->get('payee'));

        $entityManager->persist($adhesion);
        $entityManager->flush();

        $this->addFlash('success', 'Statut d\'adhésion mis à jour.');

        return $this->redirectToRoute('app_licencie_index', ['saison' => $saison->getId()]);
    }

    #[Route('/nouveau', name: 'app_licencie_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        InvitationMailer $invitationMailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email');
            $prenom = (string) $request->request->get('prenom');
            $nom = (string) $request->request->get('nom');
            $roles = $request->request->all('roles');

            $licencie = (new Licencie())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR])))
                ->setMustChangePassword(true);

            // Mot de passe temporaire inutilisable : le licencié le définit lui-même via le lien d'activation.
            $licencie->setPassword($passwordHasher->hashPassword($licencie, bin2hex(random_bytes(32))));

            $token = $licencie->generateActivationToken();

            $entityManager->persist($licencie);
            $entityManager->flush();

            $invitationMailer->envoyerInvitation($licencie, $token);

            $this->addFlash('success', sprintf('Licencié créé, un email d\'activation a été envoyé à %s.', $email));

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => null,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_licencie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Licencie $licencie, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $roles = $request->request->all('roles');
            $dateNaissance = (string) $request->request->get('dateNaissance');
            $numeroLicence = (string) $request->request->get('numeroLicence');

            $licencie
                ->setEmail((string) $request->request->get('email'))
                ->setPrenom((string) $request->request->get('prenom'))
                ->setNom((string) $request->request->get('nom'))
                ->setRoles(array_values(array_intersect($roles, [Licencie::ROLE_BUREAU, Licencie::ROLE_ENTRAINEUR])))
                ->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null)
                ->setNumeroLicence($numeroLicence ?: null);

            $entityManager->flush();

            $this->addFlash('success', 'Licencié modifié.');

            return $this->redirectToRoute('app_licencie_index');
        }

        return $this->render('licencie/form.html.twig', [
            'licencie' => $licencie,
        ]);
    }

    #[Route('/{id}/classement', name: 'app_licencie_classement', methods: ['POST'])]
    public function classement(Licencie $licencie, FfbadClassementService $classementService, EntityManagerInterface $entityManager): Response
    {
        $classementService->mettreAJourClassement($licencie);
        $entityManager->flush();

        $this->addFlash('success', 'Classement mis à jour.');

        return $this->redirectToRoute('app_licencie_index');
    }
}
