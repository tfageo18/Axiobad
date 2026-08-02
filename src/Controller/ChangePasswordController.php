<?php

namespace App\Controller;

use App\Entity\Licencie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ChangePasswordController extends AbstractController
{
    #[Route('/mon-compte/mot-de-passe', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            $newPassword = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

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
