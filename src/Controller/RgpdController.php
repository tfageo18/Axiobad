<?php

namespace App\Controller;

use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RgpdController extends AbstractController
{
    #[Route('/confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->renderMarkdown('Politique de confidentialité', 'politique-confidentialite.md');
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'])]
    public function mentionsLegales(): Response
    {
        return $this->renderMarkdown('Mentions légales', 'mentions-legales.md');
    }

    #[Route('/registre-traitements', name: 'app_registre_traitements', methods: ['GET'])]
    public function registreTraitements(): Response
    {
        return $this->renderMarkdown('Registre des traitements', 'registre-traitements.md');
    }

    private function renderMarkdown(string $titre, string $fichier): Response
    {
        $chemin = $this->getParameter('kernel.project_dir').'/docs/'.$fichier;
        $markdown = is_file($chemin) ? file_get_contents($chemin) : '# Page indisponible';

        $converter = new CommonMarkConverter();
        $html = $converter->convert($markdown)->getContent();

        return $this->render('documentation/index.html.twig', [
            'titre' => $titre,
            'contenu' => $html,
        ]);
    }
}
