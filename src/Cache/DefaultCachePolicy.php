<?php

namespace Bnine\FilesBundle\Cache;

/**
 * Implémentation par defaut de CachePolicyInterface.
 *
 * Retourne null pour toutes les methodes, ce qui signifie : "utiliser
 * la valeur par defaut du bundle" :
 *   - max-age = 30 jours (2592000 secondes)
 *   - Cache-Control: public
 *
 * Si une application hote veut une politique de cache differente
 * (ex: avatars en private+5min, logos en public+1an), elle implemente
 * CachePolicyInterface et declare son service comme alias de
 * Bnine\FilesBundle\Cache\CachePolicyInterface.
 */
class DefaultCachePolicy implements CachePolicyInterface
{
    public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int
    {
        return null;
    }

    public function isPublicCacheable(string $domain, string $id, string $filePath): ?bool
    {
        return null;
    }
}