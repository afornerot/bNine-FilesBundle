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

## Solution : firewall stateless dédié

Dans `config/packages/security.yaml`, déclarez un firewall `security: false`
et `stateless: true` pour les routes publiques **avant** le firewall
principal. Restreignez le pattern aux seules routes qui doivent être
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

        main:
            pattern: ^/
            # ... votre config classique ...
```

> ⚠️ Ne mettez **PAS** tout `^/bninefiles` en `security: false` : cela
> exposerait aussi les routes d'upload, de suppression et de crop, qui
> doivent rester protégées par `FileVoter`.

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

## Alternative : CachePolicy custom

Si pour une raison quelconque vous ne pouvez pas toucher à la config
firewall (par ex. reverse-proxy qui injecte la session), vous pouvez
toujours surcharger la stratégie de cache du bundle en implémentant
`Bnine\FilesBundle\Cache\CachePolicyInterface` (voir [cache-policy.md](cache-policy.md)).
Cela ne changera rien au problème de fond (Symfony force `private`),
mais peut être combiné avec un `setSharedMaxAge()` ou des headers
`Vary: Cookie` selon votre infra.