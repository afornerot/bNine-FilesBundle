# Stratégie de cache HTTP (`CachePolicyInterface`)

Par défaut, les routes `bninefiles_files_image` et `bninefiles_files_thumbnail` répondent avec :

```
Cache-Control: max-age=2592000, public
```

(30 jours, public : le fichier peut être caché par tous les caches — navigateur, CDN, reverse proxy.)

L'application hôte peut surcharger cette politique fichier par fichier en implémentant `Bnine\FilesBundle\Cache\CachePolicyInterface`. Le bundle distingue clairement :

- **`FileVoter`** : décide **QUI** peut accéder au fichier (sécurité)
- **`CachePolicy`** : décide **COMBIEN DE TEMPS** le fichier peut être caché (performance)

## Interface `CachePolicyInterface`

```php
namespace App\Cache;

use Bnine\FilesBundle\Cache\CachePolicyInterface;

class MyCachePolicy implements CachePolicyInterface
{
    /**
     * Duree du cache en secondes, ou null pour utiliser la valeur
     * par defaut du bundle (30 jours = 2592000).
     *
     *   - 0   : pas de cache (header Cache-Control non envoye)
     *   - 60  : cache 1 minute
     *   - 86400 : cache 1 jour
     *   - null : defaut du bundle
     */
    public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int
    {
        // Avatars/logos : cache court (5 min) car susceptibles de changer
        if (in_array($domain, ['avatar', 'logo'], true)) {
            return 300;
        }
        return null; // defaut du bundle
    }

    /**
     * Indique si la reponse doit etre 'public' (cacheable par tous) ou
     * 'private' (cacheable uniquement par le navigateur de l'utilisateur).
     *
     *   - true  : setPublic() (defaut)
     *   - false : setPrivate()
     *   - null  : defaut du bundle (public)
     */
    public function isPublicCacheable(string $domain, string $id, string $filePath): ?bool
    {
        // Domaines sensibles : private (pas de cache CDN)
        if (in_array($domain, ['avatar', 'logo'], true)) {
            return false;
        }
        return null; // defaut du bundle
    }
}
```

## Enregistrement du service

Dans `config/services.yaml` :

```yaml
services:
    # Remplace l'implementation par defaut du bundle
    Bnine\FilesBundle\Cache\CachePolicyInterface: '@App\Cache\MyCachePolicy'
```

Ou, si vous voulez definir explicitement votre service :

```yaml
services:
    App\Cache\MyCachePolicy: ~

    Bnine\FilesBundle\Cache\CachePolicyInterface: '@App\Cache\MyCachePolicy'
```

## Routes concernées

Le `CachePolicy` est appliqué sur :

- `bninefiles_files_image` (`GET /bninefiles/image/{domain}/{id}?path=...`)
- `bninefiles_files_thumbnail` (`GET /bninefiles/thumbnail/{domain}/{id}?path=...`)

**Pas appliqué** sur `bninefiles_files_download` : un téléchargement ne doit pas être mis en cache public par les proxys.

## Granularité

L'interface reçoit `(domain, id, filePath)` ce qui permet de définir des règles très fines :

```php
public function getCacheMaxAge(string $domain, string $id, string $filePath): ?int
{
    // Règle par domain
    if ($domain === 'avatar') return 60;          // 1 minute pour les avatars
    if ($domain === 'blog-image') return 86400;    // 1 jour pour les images de blog
    if ($domain === 'static') return 31536000;     // 1 an pour les assets statiques

    // Règle par fichier spécifique (ex: logo qui change souvent)
    if ($domain === 'logo' && str_contains($filePath, 'campaign-')) {
        return 0; // pas de cache pour les logos de campagnes
    }

    return null; // defaut du bundle
}
```

## Rétrocompatibilité

Si vous ne déclarez aucun `CachePolicyInterface` custom, le bundle utilise `DefaultCachePolicy` qui retourne `null` partout → comportement par défaut inchangé (30 jours, public). Aucun breaking change pour les apps existantes.

## ⚠️ CachePolicy ≠ bypass du SessionListener

Implémenter `CachePolicyInterface` modifie les directives `Cache-Control`
posées par le bundle, **mais** Symfony `AbstractSessionListener` peut
écraser ces directives par `Cache-Control: private, max-age=0` dès qu'une
session est démarrée (cas typique : un firewall principal capturant
`/bninefiles/...` ou une session anonyme).

Pour un cache navigateur réellement actif en production, voir
[firewall-cache.md](firewall-cache.md) qui documente firewall stateless
dédié + `context` et EventSubscriber `kernel.response`.