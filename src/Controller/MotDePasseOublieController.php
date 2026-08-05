<?php

namespace App\Controller;

use App\Repository\LicencieRepository;
use App\Service\InvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MotDePasseOublieController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, LicencieRepository $licencieRepository, EntityManagerInterface $entityManager, InvitationMailer $invitationMailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email');
            $licencie = $email ? $licencieRepository->findOneBy(['email' => $email]) : null;

            if ($licencie && $licencie->aUnCompte() && $licencie->isActif()) {
                $token = $licencie->generateActivationToken();
                $entityManager->flush();
                $invitationMailer->envoyerReinitialisationMotDePasse($licencie, $token);
            }

            // Message générique dans tous les cas (compte inexistant, inactif ou sans email) :
            // ne pas révéler si une adresse email correspond à un compte existant.
            $this->addFlash('success', "Si un compte existe avec cette adresse email, un lien de réinitialisation vient de lui être envoyé.");

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/mot_de_passe_oublie.html.twig');
    }
}
