<?php

namespace App\Controller;

use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocumentationController extends AbstractController
{
    #[Route('/documentation', name: 'app_documentation', methods: ['GET'])]
    public function __invoke(): Response
    {
        $cheminGuide = $this->getParameter('kernel.project_dir').'/docs/guide-utilisation.md';
        $markdown = is_file($cheminGuide) ? file_get_contents($cheminGuide) : '# Documentation indisponible';

        $converter = new CommonMarkConverter();
        $html = $converter->convert($markdown)->getContent();

        return $this->render('documentation/index.html.twig', [
            'contenu' => $html,
        ]);
    }
}
