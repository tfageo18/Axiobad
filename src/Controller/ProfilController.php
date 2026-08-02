<?php

namespace App\Controller;

use App\Badminton\ClassementFfbad;
use App\Entity\Licencie;
use App\Repository\LicencieRepository;
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
        LicencieRepository $licencieRepository,
        SluggerInterface $slugger,
    ): Response {
        /** @var Licencie $licencie */
        $licencie = $this->getUser();

        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email');
            $telephone = (string) $request->request->get('telephone');
            $dateNaissance = (string) $request->request->get('dateNaissance');
            $numeroLicence = (string) $request->request->get('numeroLicence');

            if ($email && $email !== $licencie->getEmail()) {
                $existant = $licencieRepository->findOneBy(['email' => $email]);
                if ($existant && $existant->getId() !== $licencie->getId()) {
                    $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');

                    return $this->redirectToRoute('app_mon_profil');
                }
                $licencie->setEmail($email);
            }

            $licencie->setTelephone($telephone ?: null);
            $licencie->setDateNaissance($dateNaissance ? new \DateTimeImmutable($dateNaissance) : null);
            $licencie->setNumeroLicence($numeroLicence ?: null);
            $licencie->setClassementSimple($this->normaliserClassement($request->request->get('classementSimple')));
            $licencie->setClassementDouble($this->normaliserClassement($request->request->get('classementDouble')));
            $licencie->setClassementMixte($this->normaliserClassement($request->request->get('classementMixte')));

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

    private function normaliserClassement(mixed $valeur): ?string
    {
        $valeur = (string) $valeur;

        return in_array($valeur, ClassementFfbad::CODES, true) ? $valeur : null;
    }
}
