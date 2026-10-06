# Configuration firewall (cache navigateur)

Les routes `bninefiles_files_image` et `bninefiles_files_thumbnail` répondent
avec `Cache-Control: max-age=2592000, public` (ou la valeur de votre
`CachePolicy`). Pour que le navigateur/CDN puisse réellement cacher ces
réponses, il faut **éviter que Symfony ne force `Cache-Control: no-cache,
private`** au niveau du session listener.

## Le problème

`Symfony\Component\HttpKernel\EventListener\AbstractSessionListener`
force `setPrivate()` + `setMaxAge(0)` + `must-revalidate` dès qu'une
session est démarrée sur la requête (comportement par défaut en `prod`).
Si votre firewall principal capture `/bninefiles/...` (par exemple avec
un pattern `^/`), toutes les requêtes sur les routes du bundle
démarrent une session → le `Cache-Control` posé par le bundle est écrasé.

## Symptômes

```
$ curl -I https://app/bninefiles/image/avatar/0?path=...
Cache-Control: max-age=0, must-revalidate, private
Last-Modified: ...
```

→ Le navigateur ne cache rien malgré le bundle.

## Solution 1 : firewall stateless dédié + `context`

Dans `config/packages/security.yaml`, déclarez un firewall `security: false`,
`stateless: true` et `context: <firewall_principal>` pour les routes
publiques. Restreignez le pattern aux seules routes qui doivent être
publiques (celles qui posent des headers `Cache-Control: public`) :

```yaml
security:
    firewalls:
        # ... autres firewalls ...

        bninefiles:
            # Routes publiques : image, thumbnail.
            # Les routes protegees (uploadmodal, uploadfile, delete, mkdir,
            # crop, browse, gallery, download) restent sous le firewall
            # principal et gardent leur FileVoter.
            pattern: ^/bninefiles/(image|thumbnail)/
            security: false
            stateless: true
            context: main    # partage le token utilisateur du firewall principal

        main:
            pattern: ^/
            # ... votre config classique ...
```

⚠️ Ne mettez **PAS** tout `^/bninefiles` en `security: false` : cela
exposerait aussi les routes d'upload, de suppression et de crop, qui
doivent rester protégées par `FileVoter`.

> ℹ️ Le `context: main` permet à `FileVoter` (et donc au contrôleur
> `FileController::image()`) de récupérer le token utilisateur authentifié
> dans le firewall principal. Sans `context`, `$token->getUser()` retournerait
> `null` et toutes les vérifications d'accès échoueraient.

⚠️ **Limitation connue** : sur certaines versions de Symfony 7.x,
`stateless: true` ne suffit pas à empêcher le `SessionListener` global de
poser ses headers `Cache-Control: private` (vérifié sur Symfony 7.4).
Dans ce cas, la Solution 2 s'impose.

## Solution 2 : EventSubscriber de cache (fallback fiable)

Si la Solution 1 ne suffit pas dans votre version de Symfony (headers
toujours écrasés), ou si vous ne pouvez pas créer un firewall dédié
(reverse-proxy, mutualisation, etc.), utilisez un `kernel.response`
listener à priorité basse pour ré-appliquer les directives de cache
**après** que Symfony les ait écrasées.

```php
<?php
// src/EventSubscriber/BninefilesCacheSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BninefilesCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité basse pour s'exécuter APRÈS AbstractSessionListener.
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/bninefiles/')) {
            return;
        }

        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            return;
        }

        // Adapter le max-age selon vos besoins.
        $maxAge = str_starts_with($path, '/bninefiles/thumbnail/')
            ? 31536000   // 1 an pour les thumbnails (régénérés à la demande)
            : 2592000;   // 30 jours pour les images

        $response->setPublic();
        $response->setMaxAge($maxAge);
    }
}
```

Ce listener s'exécute **après** le `SessionListener` (priorité par défaut
0) et **après** le `RouterListener` (priorité 32 sur `kernel.request`
mais inoffensif sur `kernel.response`). Il écrase proprement les
directives `private` et `max-age=0` que Symfony aurait pu poser.

Le service est auto-taggé par `autoconfigure: true` (cf.
`config/services.yaml` du projet hôte).

## Vérification

```bash
$ curl -sI https://app/bninefiles/image/avatar/0?path=... | grep -i cache-control
Cache-Control: max-age=2592000, public
```

Et pour s'assurer qu'une route protégée redirige toujours vers le login :

```bash
$ curl -sI https://app/bninefiles/uploadmodal/avatar/0?crop=1 | head -1
HTTP/1.1 302 Found
Location: /login
```

## Combiner les deux solutions

Dans la pratique, combiner les deux est la meilleure approche :

- **Solution 1** (firewall dédié) :
  - Évite le démarrage de session sur `/bninefiles/(image|thumbnail)/`
  - Partage le token utilisateur via `context: main`
  - Le contrôleur peut faire `denyAccessUnlessGranted(VIEW, [...])` correctement

- **Solution 2** (EventSubscriber) :
  - Garantit que les headers `Cache-Control` du bundle ne sont pas écrasés
  - Marche même si le firewall principal capture la requête
  - Marche même en présence d'un reverse-proxy

C'est la configuration recommandée pour les projets en production où les
performances de cache navigateur sont critiques.

## Alternative : CachePolicy custom

Si pour une raison quelconque vous ne pouvez pas toucher à la config
firewall (par ex. reverse-proxy qui injecte la session), vous pouvez
toujours surcharger la stratégie de cache du bundle en implémentant
`Bnine\FilesBundle\Cache\CachePolicyInterface` (voir [cache-policy.md](cache-policy.md)).
Cela ne changera rien au problème de fond (Symfony force `private`),
mais peut être combiné avec un `setSharedMaxAge()` ou des headers
`Vary: Cookie` selon votre infra.