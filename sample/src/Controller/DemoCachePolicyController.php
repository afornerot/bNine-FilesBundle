<?php

namespace App\Controller;

use Bnine\FilesBundle\Cache\CachePolicyInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Page de demonstration du CachePolicyInterface.
 *
 * Affiche la politique de cache appliquee pour differentes routes du bundle.
 * On utilise directement les methodes du service injecte pour montrer
 * ce que le bundle enverrait comme Cache-Control.
 */
class DemoCachePolicyController extends AbstractController
{
    #[Route('/cache-policy', name: 'demo_cache_policy')]
    public function index(CachePolicyInterface $cachePolicy): Response
    {
        $cacheClass = $cachePolicy::class;

        // Echantillons : fichiers dedies crees par seed.sh pour la demo CachePolicy.
        // - avatar/0/cache-demo-avatar.jpg : domaine sensible (private + 5min via SampleCachePolicy)
        // - sample/1/cache-demo-sample.jpg : domaine public (public + 30j defaut bundle)
        $samples = [
            ['label' => 'Avatar (domain=avatar, sensible)', 'domain' => 'avatar', 'id' => '0', 'path' => 'cache-demo-avatar.jpg'],
            ['label' => 'Sample (domain=sample, public)', 'domain' => 'sample', 'id' => '1', 'path' => 'cache-demo-sample.jpg'],
        ];

        // On ne conserve que les samples dont le fichier existe reellement
        // (au cas ou le seed n'aurait pas ete execute ou si un fichier a ete supprime).
        $decisions = [];
        foreach ($samples as $sample) {
            $filePath = $this->getParameter('kernel.project_dir')
                .'/uploads/'.$sample['domain'].'/'.$sample['id'].'/'.$sample['path'];

            if (!is_file($filePath)) {
                continue; // fichier absent, on n'affiche pas la ligne
            }

            $maxAge = $cachePolicy->getCacheMaxAge($sample['domain'], $sample['id'], $sample['path']);
            $isPublic = $cachePolicy->isPublicCacheable($sample['domain'], $sample['id'], $sample['path']);

            // Valeur qui sera appliquee par le bundle (defaut si null)
            $appliedMaxAge = $maxAge ?? (86400 * 30); // 30 jours
            $appliedPublic = $isPublic ?? true;

            $decisions[] = [
                'label' => $sample['label'],
                'url' => $this->generateUrl('bninefiles_files_image', [
                    'domain' => $sample['domain'],
                    'id' => $sample['id'],
                ]) . '?path=' . urlencode($sample['path']),
                'raw_max_age' => $maxAge,
                'raw_public' => $isPublic,
                'applied_max_age' => $appliedMaxAge,
                'applied_public' => $appliedPublic,
                'cache_control' => sprintf(
                    'max-age=%d, %s',
                    $appliedMaxAge,
                    $appliedPublic ? 'public' : 'private'
                ),
            ];
        }

        return $this->render('demo/cache_policy.html.twig', [
            'cache_class' => $cacheClass,
            'decisions' => $decisions,
        ]);
    }
}