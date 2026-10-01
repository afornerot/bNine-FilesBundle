# Installation

## 1. Composer

```bash
composer require bnine/filesbundle
```

## 2. Activer le bundle

Dans `config/bundles.php` :

```php
return [
    // ...
    Bnine\FilesBundle\BnineFilesBundle::class => ['all' => true],
];
```

## 3. Charger les routes du bundle

Le bundle expose 11 routes sous le préfixe `/bninefiles`. Ajoutez dans votre `config/routes.yaml` :

```yaml
bnine_files:
    resource:
        path: ../vendor/bnine/filesbundle/src/Controller/
        namespace: Bnine\FilesBundle\Controller
    type: attribute
    prefix: /bninefiles
```

## 4. Activer le theme Twig pour `SelectFileType`

Dans `config/packages/twig.yaml` :

```yaml
twig:
    # Permet d'utiliser @BnineFilesBundle/... dans les templates
    paths:
        '%kernel.project_dir%/vendor/bnine/filesbundle/templates': BnineFilesBundle
        '%kernel.project_dir%/vendor/bnine/filesbundle/Resources/public': BnineFilesPublic
    # Active le theme fourni pour rendre SelectFileType correctement
    form_themes:
        - '@BnineFilesBundle/Form/_theme.html.twig'
```

Le theme surcharge les blocs `hidden_widget` et `hidden_row` pour intercepter uniquement les champs dont le `block_prefixes` contient `select_file`. Les autres `HiddenType` ne sont pas affectés.

## 5. Configurer `FileService` (stockage)

Dans `config/services.yaml` :

```yaml
Bnine\FilesBundle\Service\FileService:
    arguments:
        $storage: '@app.storage'              # service Flysystem (optionnel)
        $s3: '%env(bool:STORAGE_S3)%'          # true pour S3, false pour local
```

Pour un stockage local, déclarez le service Flysystem dans `config/packages/flysystem.yaml` :

```yaml
flysystem:
    storages:
        app.storage:
            adapter: 'local'
            options:
                directory: '%kernel.project_dir%/uploads'
```

Pour S3 :

```yaml
flysystem:
    storages:
        app.storage:
            adapter: 'aws-s3-v3'
            options:
                client: '%app.s3_client%'

services:
    app.s3_client:
        class: Aws\S3\S3Client
        arguments:
            -
                endpoint: '%env(S3_ENDPOINT)%'
                region: '%env(S3_REGION)%'
                credentials:
                    key: '%env(S3_ACCESS_KEY)%'
                    secret: '%env(S3_SECRET_KEY)%'
                use_path_style_endpoint: true
```

## 6. Variables d'environnement

`.env.local` :

```env
# Local (par défaut)
STORAGE_DSN=local://uploads
STORAGE_S3=0

# ou S3
STORAGE_S3=1
S3_ENDPOINT=http://minio:9000
S3_BUCKET=my-uploads
S3_ACCESS_KEY=changeme
S3_SECRET_KEY=changeme
S3_REGION=us-east-1
```

## 7. Voter de sécurité (obligatoire)

Le bundle n'embarque pas de voter concret. Vous devez implémenter `Bnine\FilesBundle\Security\AbstractFileVoter`. Voir [voter.md](voter.md).

## 8. Assets JS / CSS

Le bundle embarque **toutes les libs tierces nécessaires** dans `Resources/public/lib/` (jQuery, Bootstrap, FontAwesome + webfonts, Dropzone, Cropper, GLightbox) **plus** ses propres assets (`bninefiles.js`, `bninefiles.css`).

Après `composer require`, exécutez :

```bash
php bin/console assets:install public
```

Tous les fichiers sont publiés sous `/bundles/bninefiles/...` :

```twig
{# Bundles assets (toujours nécessaires) #}
bundles/bninefiles/js/bninefiles.js
bundles/bninefiles/css/bninefiles.css

{# Libs tierces embarquées (selon vos besoins) #}
bundles/bninefiles/lib/js/jquery.min.js
bundles/bninefiles/lib/js/bootstrap.bundle.min.js
bundles/bninefiles/lib/css/bootstrap.min.css
bundles/bninefiles/lib/css/fontawesome.min.css
bundles/bninefiles/lib/webfonts/fa-solid-900.woff2
bundles/bninefiles/lib/webfonts/fa-regular-400.woff2
bundles/bninefiles/lib/webfonts/fa-brands-400.woff2
bundles/bninefiles/lib/css/glightbox.min.css
bundles/bninefiles/lib/js/glightbox.min.js
bundles/bninefiles/lib/css/dropzone.min.css
bundles/bninefiles/lib/js/dropzone.min.js
bundles/bninefiles/lib/css/cropper.min.css
bundles/bninefiles/lib/js/cropper.min.js
```

**Total publié** : ~1 MB. Pas de build, pas de Node.js, pas de CDN requis — tout fonctionne offline.

## 9. Charger les libs dans votre layout

L'app hôte inclut les libs nécessaires dans son layout principal (`base.html.twig`) :

```twig
{# templates/base.html.twig #}
<link rel="stylesheet" href="{{ asset('bundles/bninefiles/lib/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('bundles/bninefiles/lib/css/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('bundles/bninefiles/lib/css/glightbox.min.css') }}">
<link rel="stylesheet" href="{{ asset('bundles/bninefiles/css/bninefiles.css') }}">

<script src="{{ asset('bundles/bninefiles/lib/js/jquery.min.js') }}"></script>
<script src="{{ asset('bundles/bninefiles/lib/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('bundles/bninefiles/lib/js/glightbox.min.js') }}"></script>
<script src="{{ asset('bundles/bninefiles/js/bninefiles.js') }}"></script>
```

Adaptez selon vos besoins : si vous n'utilisez pas la gallery, vous pouvez omettre GLightbox ; si vous n'utilisez pas IconUploadType avec crop, vous pouvez omettre Cropper.js, etc.

### Cas particulier : les iframes d'upload et de crop

Les templates `templates/file/upload.html.twig` et `templates/file/crop.html.twig` du bundle sont rendus en iframe standalone (`/bninefiles/uploadmodal/...` et `/bninefiles/crop-page/...`). Ils n'héritent pas du layout principal et ont besoin de jQuery + Bootstrap + Dropzone (upload) ou Cropper (crop).

Le bundle inclut donc automatiquement un partial surchargeable `@BnineFiles/partials/_iframe_assets.html.twig` qui charge ces libs depuis `bundles/bninefiles/lib/...`.

**Pour pointer vers vos propres assets buildés** (Webpack, AssetMapper, CDN), surchargez ce partial dans votre application :

```twig
{# templates/bundles/BnineFilesBundle/partials/_iframe_assets.html.twig #}
{% if _iframe_context == 'upload' %}
    <script src="{{ asset('build/dropzone.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('build/dropzone.css') }}">
    {# ... etc, vos propres assets ... #}
{% elseif _iframe_context == 'crop' %}
    <script src="{{ asset('build/cropper.js') }}"></script>
    {# ... etc ... #}
{% endif %}
```

Le partial reçoit la variable `_iframe_context` qui vaut `'upload'` ou `'crop'` selon le template qui l'inclut.

### Alternative : surcharger le template complet

Vous pouvez aussi surcharger directement les templates du bundle en copiant :

```
templates/bundles/BnineFilesBundle/file/upload.html.twig
templates/bundles/BnineFilesBundle/file/crop.html.twig
```

dans votre app et en y adaptant le chargement des libs comme vous le souhaitez.