<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Security\PasswordStrengthChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ChangePasswordController extends AbstractController
{
    #[Route('/mon-compte/mot-de-passe', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, PasswordStrengthChecker $passwordStrengthChecker): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('change-password', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('app_change_password');
            }

            $currentPassword = (string) $request->request->get('current_password');
            if (!$passwordHasher->isPasswordValid($licencie, $currentPassword)) {
                $this->addFlash('error', 'Mot de passe actuel incorrect.');

                return $this->redirectToRoute('app_change_password');
            }

            $newPassword = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            $erreurMotDePasse = $passwordStrengthChecker->verifier($newPassword);
            if (null !== $erreurMotDePasse) {
                $this->addFlash('error', $erreurMotDePasse);

                return $this->redirectToRoute('app_change_password');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');

                return $this->redirectToRoute('app_change_password');
            }

            $licencie->setPassword($passwordHasher->hashPassword($licencie, $newPassword));
            $licencie->setMustChangePassword(false);
            $entityManager->flush();

            $this->addFlash('success', 'Mot de passe mis à jour.');

            return $this->redirectToRoute('app_creneau_index');
        }

        return $this->render('security/change_password.html.twig', [
            'forced' => $licencie->mustChangePassword(),
        ]);
    }
}
