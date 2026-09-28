<?php

namespace App\Controller;

use App\Markdown\CustomSlugNormalizer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemoReadmeController extends AbstractController
{
    #[Route('/readme', name: 'demo_readme')]
    public function readme(): Response
    {
        // Le README.md du bundle est bind-monte sur vendor/bnine/filesbundle/README.md
        // via le docker-compose.yml du sample (path: ../:/var/www/html/vendor/bnine/filesbundle:ro).
        $readmePath = $this->getParameter('kernel.project_dir').'/vendor/bnine/filesbundle/README.md';

        if (!file_exists($readmePath)) {
            throw $this->createNotFoundException('README.md du bundle introuvable. Avez-vous lance `composer install` ?');
        }

        $markdown = file_get_contents($readmePath);

// GitHub Flavored Markdown + extension HeadingPermalink pour que les liens
        // internes (#section) fonctionnent (ajoute un attribut id sur chaque heading).
        // CommonMark v2.x : il faut explicitement ajouter CommonMarkCoreExtension
        // pour les renderers de base (Document, Paragraph, Text, etc.).
        // id_prefix='' : pas de prefixe 'content-' pour matcher les ancres du TOC du README.
        // symbol='' : pas d'icone ¶ visible à côté des headings.
        // slug_normalizer est au niveau top-level (pas dans heading_permalink).
        $config = [
            'heading_permalink' => [
                'id_prefix' => '',
                'fragment_prefix' => '',
                'symbol' => '',
            ],
            'slug_normalizer' => [
                'instance' => new CustomSlugNormalizer(),
            ],
        ];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $converter = new MarkdownConverter($environment);
        $html = $converter->convert($markdown)->getContent();

        return $this->render('demo/readme.html.twig', [
            'readme_html' => $html,
            'bundle_root' => dirname($readmePath),
        ]);
    }
}