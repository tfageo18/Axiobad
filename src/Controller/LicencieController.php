<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
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
    public function index(LicencieRepository $licencieRepository): Response
    {
        return $this->render('licencie/index.html.twig', [
            'licencies' => $licencieRepository->findAll(),
        ]);
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
