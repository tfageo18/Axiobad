<?php

namespace App\Controller;

use League\CommonMark\GithubFlavoredMarkdownConverter;
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

        // GithubFlavoredMarkdownConverter (et non CommonMarkConverter) : indispensable pour que
        // les tableaux markdown (ex. rôles et permissions) soient bien rendus en <table>.
        $converter = new GithubFlavoredMarkdownConverter();
        $html = $converter->convert($markdown)->getContent();

        return $this->render('documentation/index.html.twig', [
            'contenu' => $html,
        ]);
    }
}
