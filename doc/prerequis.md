# Pré-requis

## Dépendances Composer

| Package | Version | Rôle |
|---------|---------|------|
| PHP | ^8.1 | Runtime |
| `symfony/framework-bundle` | ^7.1 | HTTP, Security, Routing, contrôleur abstrait |
| `symfony/form` | ^7.1 | Form Types (`IconUploadType`, `SelectFileType`) + `OptionsResolver` |
| `symfony/filesystem` | ^7.1 | Création de dossiers (`Filesystem`, `IOExceptionInterface`) |
| `symfony/finder` | ^7.1 | Listing des fichiers locaux |
| `symfony/http-foundation` | ^7.1 | `Request`, `Response`, `JsonResponse`, `UploadedFile`, etc. |
| `symfony/http-kernel` | ^7.1 | `AbstractBundle`, `KernelInterface`, exceptions HTTP |
| `symfony/mime` | ^7.1 | `MimeTypes` (détection du type MIME) |
| `symfony/options-resolver` | ^7.1 | Validation des options des Form Types |
| `symfony/routing` | ^7.1 | `Route`, `UrlGeneratorInterface` |
| `symfony/security-core` | ^7.1 | `TokenInterface`, `Voter` (sécurité par voter) |
| `imagine/imagine` | ^1.0 | Génération de thumbnails + crop (via GD/Imagick) |
| `league/flysystem` | ^3.0 | Abstraction du stockage |
| `league/flysystem-bundle` | ^3.0 | Intégration Symfony de Flysystem |
| `league/flysystem-aws-s3-v3` | ^3.0 | Optionnel : support S3 |
| `twig/twig` | ^3.0 | Rendu des templates + extension Twig `bninefile()` |
| `psr/log` | ^1.0\|^2.0\|^3.0 | `LoggerInterface` (logs d'erreurs dans `FileController`) |

> Toutes ces dépendances sont déclarées explicitement (pas via transitivité) pour garantir la stabilité du bundle face aux changements de `framework-bundle`.

## Bundles Symfony à activer

Dans `config/bundles.php` :

| Bundle | Obligatoire | Rôle |
|--------|-------------|------|
| `Symfony\Bundle\FrameworkBundle\FrameworkBundle` | oui | Active tous les composants Symfony utilisés |
| `Symfony\Bundle\TwigBundle\TwigBundle` | oui | Active Twig (templates + extension `bninefile()`) |
| `Symfony\Bundle\SecurityBundle\SecurityBundle` | oui | Active le système de sécurité (voter requis) |
| `Bnine\FilesBundle\BnineFilesBundle` | oui | Le bundle lui-même |

## Libs tierces embarquées

| Lib | Version | Usage | Chemin publié |
|-----|---------|-------|---------------|
| jQuery | 3.7+ | AJAX navigateur/gallery, modale dossier Bootstrap | `bundles/bninefiles/lib/js/jquery.min.js` |
| Bootstrap | 5.3+ | Classes CSS utilitaires + modale « Créer un dossier » | `bundles/bninefiles/lib/{js,css}/bootstrap*` |
| FontAwesome | 6.5+ | Icônes (et fonts `.woff2`) | `bundles/bninefiles/lib/css/fontawesome.min.css` + `bundles/bninefiles/lib/webfonts/*` |
| Dropzone.js | 5.9+ | Upload drag&drop dans la modale | `bundles/bninefiles/lib/{js,css}/dropzone*` |
| Cropper.js | 1.6+ | Recadrage d'image | `bundles/bninefiles/lib/{js,css}/cropper*` |
| GLightbox | 3.x | Lightbox de la galerie | `bundles/bninefiles/lib/{js,css}/glightbox*` |

## Assets bundle natifs

| Asset | Chemin publié |
|-------|---------------|
| `bninefiles.js` | `bundles/bninefiles/js/bninefiles.js` |
| `bninefiles.css` | `bundles/bninefiles/css/bninefiles.css` |

## Qui charge quoi ?

- **Application hôte** : charge **toutes les libs ci-dessus** dans son layout principal (`base.html.twig`). C'est elle qui décide si elle utilise `bundles/bninefiles/lib/...` (publié par `assets:install`) ou ses propres assets buildés (Webpack/AssetMapper/CDN).
- **Bundle** : ne charge **aucune lib tierce** dans les templates destinés à être inclus via `render(controller(...))` (browse, gallery, widget `_select.html.twig`, Form Types).
- **Partial surchargeable** : pour les 2 templates d'iframe standalone (`upload.html.twig`, `crop.html.twig`) — qui ne peuvent pas hériter du layout principal — le bundle inclut un partial `@BnineFiles/partials/_iframe_assets.html.twig` qui charge les libs minimales depuis `bundles/bninefiles/lib/...`. **L'app hôte peut surcharger ce partial** pour pointer vers ses propres assets (voir [installation.md](installation.md#9-charger-les-libs-dans-votre-layout)).

Le sample `sample/` utilise directement les assets publiés par `assets:install` et fournit un `setup.sh` qui seed des images placeholder au premier démarrage.