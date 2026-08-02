<?php

namespace App\Controller;

use App\Repository\LicencieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ActivationController extends AbstractController
{
    #[Route('/activation/{token}', name: 'app_activation', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request, LicencieRepository $licencieRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $licencie = $licencieRepository->findOneByActivationToken($token);

        if (!$licencie || !$licencie->isActivationTokenValid()) {
            $this->addFlash('error', 'Ce lien d\'activation est invalide ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $newPassword = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->redirectToRoute('app_activation', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');

                return $this->redirectToRoute('app_activation', ['token' => $token]);
            }

            $licencie->setPassword($passwordHasher->hashPassword($licencie, $newPassword));
            $licencie->setMustChangePassword(false);
            $licencie->clearActivationToken();
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte est activé, vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/activation.html.twig', [
            'token' => $token,
            'licencie' => $licencie,
        ]);
    }
}
