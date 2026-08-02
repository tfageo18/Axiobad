<?php

namespace App\Controller;

use App\Entity\Licencie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfilController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_mon_profil', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): Response {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            $telephone = (string) $request->request->get('telephone');
            $dateNaissance = (string) $request->request->get('dateNaissance');
            $numeroLicence = (string) $request->request->get('numeroLicence');

            $licencie->setTelephone($telephone ?: null);
            $licencie->setNumeroLicence($numeroLicence ?: null);
            $licencie->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null);

            $photoFile = $request->files->get('photo');
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = sprintf('licencie-%d-%s.%s', $licencie->getId(), $safeFilename, $photoFile->guessExtension());

                try {
                    $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/photos';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0755, true);
                    }
                    $photoFile->move($uploadsDir, $newFilename);
                    $licencie->setPhoto('/uploads/photos/'.$newFilename);
                } catch (FileException) {
                    $this->addFlash('error', "Erreur lors de l'envoi de la photo.");
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_mon_profil');
        }

        return $this->render('licencie/profil.html.twig', [
            'licencie' => $licencie,
        ]);
    }
}
