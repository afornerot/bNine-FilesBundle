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

class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_index')]
    public function index(): Response
    {
        // Le README.md du bundle est bind-monte sur vendor/bnine/filesbundle/README.md
        // via le docker-compose.yml du sample (path: ../:/var/www/html/vendor/bnine/filesbundle:ro).
        $readmePath = $this->getParameter('kernel.project_dir').'/vendor/bnine/filesbundle/README.md';
        $readmeHtml = '';

        if (file_exists($readmePath)) {
            $markdown = file_get_contents($readmePath);
            // GitHub Flavored Markdown + extension HeadingPermalink pour que les liens
            // internes (#section) fonctionnent (ajoute un attribut id sur chaque heading).
            $config = [
                'heading_permalink' => [
                    'id_prefix' => '',
                    'fragment_prefix' => '',
                    'symbol' => '',
                ],
                // slug_normalizer est une section de config au niveau de
                // l'Environment, PAS imbriquee dans heading_permalink.
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
            $readmeHtml = $converter->convert($markdown)->getContent();
        }

        return $this->render('demo/index.html.twig', [
            'readme_html' => $readmeHtml,
        ]);
    }

    #[Route('/browse', name: 'demo_browse')]
    public function browse(): Response
    {
        return $this->render('demo/browse.html.twig', [
            'domain' => 'sample',
            'entity_id' => 1,
        ]);
    }

    #[Route('/gallery', name: 'demo_gallery')]
    public function gallery(): Response
    {
        return $this->render('demo/gallery.html.twig', [
            'domain' => 'sample',
            'entity_id' => 1,
        ]);
    }

    #[Route('/icon', name: 'demo_icon')]
    public function icon(): Response
    {
        return $this->render('demo/icon.html.twig', [
            'domain' => 'avatar',
            'entity_id' => 0,
        ]);
    }

    #[Route('/select-demo-single', name: 'demo_select_single')]
    public function selectDemoSingle(): Response
    {
        return $this->render('demo/select_demo_single.html.twig', [
            'domain' => 'sample',
            'entity_id' => 1,
            'image' => null,
        ]);
    }

    #[Route('/select-demo-multiple', name: 'demo_select_multiple')]
    public function selectDemoMultiple(): Response
    {
        return $this->render('demo/select_demo_multiple.html.twig', [
            'domain' => 'sample',
            'entity_id' => 1,
            'images' => [],
        ]);
    }

    #[Route('/select-demo-multi', name: 'demo_select_multi')]
    public function selectDemoMulti(): Response
    {
        // Redirige vers /select-demo-multiple (page équivalente)
        return $this->redirectToRoute('demo_select_multiple');
    }
}
