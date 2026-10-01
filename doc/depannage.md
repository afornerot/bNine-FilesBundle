# Dépannage

**`AccessDeniedException` après upload / accès au navigateur.**
Vous n'avez pas implémenté `FileVoter` ou le voter n'est pas tagué `security.voter`. Symfony refuse alors tout accès par défaut.

```yaml
# config/services.yaml
App\Security\FileVoter:
    tags: ['security.voter']
```

**`Class Bnine\FilesBundle\Twig\BnineFileExtension not found`.**
Videz le cache : `php bin/console cache:clear`.

**Les miniatures ne se créent pas.**
L'extension PHP `gd` (ou `imagick`) doit être installée, avec le support JPEG :
```bash
php -m | grep -i gd
# ou
php -m | grep -i imagick
```

**Upload en mode S3 échoue silencieusement.**
Vérifiez les credentials (`S3_ACCESS_KEY`, `S3_SECRET_KEY`) et l'accessibilité réseau vers `S3_ENDPOINT` depuis votre app. En debug, vérifiez les logs Symfony.

**La galerie ne sélectionne rien.**
Vous devez définir `window.bnineFileSelect = function(data) {...}` globalement **avant** d'ouvrir la modale :
```html
<script>window.bnineFileSelect = function(items) { /* ... */ };</script>
```

**`SelectFileType` rend juste un input caché au lieu du widget.**
Le theme Twig `@BnineFilesBundle/Form/_theme.html.twig` n'est pas activé dans `config/packages/twig.yaml`. Voir [installation.md](installation.md#4-activer-le-theme-twig-pour-selectfiletype).

**Le crop génère une image floue.**
Augmentez `crop_min_size` (par défaut 300px) ou uploadez une image source plus grande.