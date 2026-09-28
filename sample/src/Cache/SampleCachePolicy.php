<?php

namespace App\Cache;

use Bnine\FilesBundle\Cache\CachePolicyInterface;

/**
 * Exemple d'implementation custom de CachePolicyInterface pour le sample.
 *
 * Demontre la granularite par (domain, id, filePath) :
 *   - Domaines 'avatar' et 'logo' : cache court (5 min) + private
 *     (sensibles, pas de cache CDN public).
 *   - Autres domaines : defaut du bundle (30 jours + public).
 *
 * Cette politique est injectee dans Bnine\FilesBundle\Cache\CachePolicyInterface
 * via l'alias dans config/services.yaml du sample.
 */
class SampleCachePolicy implements CachePolicyInterface
{
    /** Domaines dont les fichiers ont une politique de cache restrictive. */
    private const SENSITIVE_DOMAINS = ['avatar', 'logo'];

    public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int
    {
        // Domaines sensibles : cache court (5 min)
        if (in_array($domain, self::SENSITIVE_DOMAINS, true)) {
            return 300;
        }

        // Pour les autres domaines, on delaisse au defaut du bundle (30 jours).
        return null;
    }

    public function isPublicCacheable(string $domain, string $id, string $filePath): ?bool
    {
        // Domaines sensibles : private (cacheable par le navigateur uniquement,
        // pas par les CDN / reverse proxies intermediaires).
        if (in_array($domain, self::SENSITIVE_DOMAINS, true)) {
            return false;
        }

        // Pour les autres, defaut du bundle (public).
        return null;
    }
}