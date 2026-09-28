# bNine-FilesBundle

Bundle Symfony pour gérer des fichiers attachés à des entités : **upload via modale**, **navigateur de fichiers**, **galerie d'images**, **recadrage (crop)**, **miniatures (thumbnails)**, **téléchargement**, **sécurité par voter**, support **local** et **S3** via Flysystem.

Toutes les routes sont préfixées par `/bninefiles`.

---

## Sommaire

1. [Pré-requis](#1-pre-requis)
2. [Installation](#2-installation)
3. [Concepts clés](#3-concepts-cles)
4. [Routes](#4-routes)
5. [Démarrage rapide : un navigateur de fichiers](#5-demarrage-rapide--un-navigateur-de-fichiers)
6. [Voter de sécurité](#6-voter-de-securite)
7. [Système de modale (JS)](#7-systeme-de-modale-js)
8. [Galerie + sélection single/multiple](#8-galerie--selection-singlemultiple)
9. [IconUploadType - Form Type](#9-iconuploadtype--form-type)
10. [SelectFileType - Form Type](#10-selectfiletype--form-type)
11. [Widget Twig @BnineFilesBundle/file/_select.html.twig](#11-widget-twig-bninefilesbundlefile_selecthtmltwig)
12. [Extension Twig bninefile()](#12-extension-twig-bninefile)
13. [Service FileService](#13-service-fileservice)
14. [Stockage (local / S3)](#14-stockage-local--s3)
15. [Sample de démonstration](#15-sample-de-demonstration)
16. [Dépannage](#16-depannage)
17. [API publique (résumé)](#17-api-publique-résumé)
18. [Stratégie de cache HTTP (`CachePolicyInterface`)](#18-stratégie-de-cache-http-cachepolicyinterface)
19. [CustomEvents JS (extension cote navigateur)](#19-customevents-js-extension-cote-navigateur)

---

## 1. Pré-requis

**Dépendances Composer** (déclarées dans le `composer.json` du bundle) :

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

**Bundles Symfony à activer** dans `config/bundles.php` :

| Bundle | Obligatoire | Rôle |
|--------|-------------|------|
| `Symfony\Bundle\FrameworkBundle\FrameworkBundle` | oui | Active tous les composants Symfony utilisés |
| `Symfony\Bundle\TwigBundle\TwigBundle` | oui | Active Twig (templates + extension `bninefile()`) |
| `Symfony\Bundle\SecurityBundle\SecurityBundle` | oui | Active le système de sécurité (voter requis) |
| `Bnine\FilesBundle\BnineFilesBundle` | oui | Le bundle lui-même |

**Libs tierces embarquées dans le bundle** (publiées via `assets:install`) :

| Lib | Version | Usage | Chemin publié |
|-----|---------|-------|---------------|
| jQuery | 3.7+ | AJAX navigateur/gallery, modale dossier Bootstrap | `bundles/bninefiles/lib/js/jquery.min.js` |
| Bootstrap | 5.3+ | Classes CSS utilitaires + modale « Créer un dossier » | `bundles/bninefiles/lib/{js,css}/bootstrap*` |
| FontAwesome | 6.5+ | Icônes (et fonts `.woff2`) | `bundles/bninefiles/lib/css/fontawesome.min.css` + `bundles/bninefiles/lib/webfonts/*` |
| Dropzone.js | 5.9+ | Upload drag&drop dans la modale | `bundles/bninefiles/lib/{js,css}/dropzone*` |
| Cropper.js | 1.6+ | Recadrage d'image | `bundles/bninefiles/lib/{js,css}/cropper*` |
| GLightbox | 3.x | Lightbox de la galerie | `bundles/bninefiles/lib/{js,css}/glightbox*` |

**Assets bundle natifs** (déjà fournis) :

| Asset | Chemin publié |
|-------|---------------|
| `bninefiles.js` | `bundles/bninefiles/js/bninefiles.js` |
| `bninefiles.css` | `bundles/bninefiles/css/bninefiles.css` |

**Qui charge quoi ?**

- **Application hôte** : charge **toutes les libs ci-dessus** dans son layout principal (`base.html.twig`). C'est elle qui décide si elle utilise `bundles/bninefiles/lib/...` (publié par `assets:install`) ou ses propres assets buildés (Webpack/AssetMapper/CDN).
- **Bundle** : ne charge **aucune lib tierce** dans les templates destinés à être inclus via `render(controller(...))` (browse, gallery, widget `_select.html.twig`, Form Types).
- **Partial surchargeable** : pour les 2 templates d'iframe standalone (`upload.html.twig`, `crop.html.twig`) — qui ne peuvent pas hériter du layout principal — le bundle inclut un partial `@BnineFiles/partials/_iframe_assets.html.twig` qui charge les libs minimales depuis `bundles/bninefiles/lib/...`. **L'app hôte peut surcharger ce partial** pour pointer vers ses propres assets (voir section 2.9).

Le sample `sample/` utilise directement les assets publiés par `assets:install` et fournit un `setup.sh` qui seed des images placeholder au premier démarrage.

---

## 2. Installation

### 2.1 Composer

```bash
composer require bnine/filesbundle
```

### 2.2 Activer le bundle

Dans `config/bundles.php` :

```php
return [
    // ...
    Bnine\FilesBundle\BnineFilesBundle::class => ['all' => true],
];
```

### 2.3 Charger les routes du bundle

Le bundle expose 11 routes sous le préfixe `/bninefiles`. Ajoutez dans votre `config/routes.yaml` :

```yaml
bnine_files:
    resource:
        path: ../vendor/bnine/filesbundle/src/Controller/
        namespace: Bnine\FilesBundle\Controller
    type: attribute
    prefix: /bninefiles
```

### 2.4 Activer le theme Twig pour `SelectFileType`

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

### 2.5 Configurer `FileService` (stockage)

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

### 2.6 Variables d'environnement

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

### 2.7 Voter de sécurité (obligatoire)

Le bundle n'embarque pas de voter concret. Vous devez implémenter `Bnine\FilesBundle\Security\AbstractFileVoter`. Voir [section 6](#6-voter-de-sécurité).

### 2.8 Assets JS / CSS

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

### 2.9 Charger les libs dans votre layout

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

**Cas particulier : les iframes d'upload et de crop**

Les templates `templates/file/upload.html.twig` et `templates/file/crop.html.twig` du bundle sont rendus en iframe standalone (`/bninefiles/uploadmodal/...` et `/bninefiles/crop-page/...`). Ils n'héritent pas du layout principal et ont besoin de jQuery + Bootstrap + Dropzone (upload) ou Cropper (crop).

Le bundle inclut donc automatiquement un partial surchargeable `@BnineFiles/partials/_iframe_assets.html.twig` qui charge ces libs depuis `bundles/bninefiles/lib/...`.

**Pour pointer vers vos propres assets buildés** (Webpack, AssetMapper, CDN), surchargez ce partial dans votre application :

```twig
{# templates/bundles/BnineFilesBundle/partials/_iframe_assets.html.twig #}
{% if _iframe_context == 'upload' %}
    <script src="{{ asset('build/dropzone.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('build/dropzone.css') }}">
    {# ... etc, vos propres assets ... %}
{% elseif _iframe_context == 'crop' %}
    <script src="{{ asset('build/cropper.js') }}"></script>
    {# ... etc ... %}
{% endif %}
```

Le partial reçoit la variable `_iframe_context` qui vaut `'upload'` ou `'crop'` selon le template qui l'inclut.

**Alternative : surcharger le template complet**

Vous pouvez aussi surcharger directement les templates du bundle en copiant :

```
templates/bundles/BnineFilesBundle/file/upload.html.twig
templates/bundles/BnineFilesBundle/file/crop.html.twig
```

dans votre app et en y adaptant le chargement des libs comme vous le souhaitez.

---

## 3. Concepts clés

| Concept | Description |
|---------|-------------|
| **`domain`** | Segment logique d'organisation (ex: `avatar`, `blog`, `gallery`, `user-document`). Sous-dossier racine du stockage. |
| **`id`** | Identifiant entier (ou string) de l'entité à laquelle les fichiers sont rattachés. Sous-dossier enfant. |
| **`path`** | Chemin relatif **à l'intérieur** de `{uploads}/{domain}/{id}/`. Ex: `photos/2024/photo.jpg`. |
| **`editable`** | `1` = mode écriture (upload, dossiers, suppression), `0` = lecture seule (navigation, téléchargement, lightbox). |
| **Domaine public** | `avatar`, `logo`, `icon` : le bundle renomme les fichiers uploadés en `bin2hex(random_bytes(16)).{ext}` et crée automatiquement un thumb (copie). |

**Arborescence type (mode local)** :

```
{uploads}/
├── {domain}/
│   └── {id}/
│       ├── fichier.jpg
│       ├── sous-dossier/
│       │   └── autre.png
│       └── _thumbs/
│           ├── fichier.jpg        # thumb auto (≥300px côté le plus petit)
│           └── 150x150/
│               └── fichier.jpg    # thumb crop (150x150 par défaut)
```

**Préfixe des routes** : toutes les routes du bundle commencent par `/bninefiles` :

```
http://app/bninefiles/list/blog/42/1      # browse éditable pour l'entité blog #42
http://app/bninefiles/gallery/avatar/7/0  # galerie en lecture seule pour l'avatar de l'entité #7
```

---

## 4. Routes

| URL | Méthode | Nom | Voter | Description |
|-----|---------|-----|-------|-------------|
| `/bninefiles/list/{domain}/{id}/{editable}` | GET | `bninefiles_files` | `view`/`edit` | Navigateur de fichiers (liste + actions) |
| `/bninefiles/uploadmodal/{domain}/{id}?path=&imageOnly=&crop=&min_size=&ratio=&configurable=` | GET | `bninefiles_files_uploadmodal` | `edit` | Page d'upload (iframe modale) |
| `/bninefiles/uploadfile?domain=&id=&path=` | POST (multipart) | `bninefiles_files_uploadfile` | `edit` | Upload du fichier (appelé par Dropzone) |
| `/bninefiles/delete/{domain}/{id}` | POST (JSON `{path}`) | `bninefiles_files_delete` | `delete` | Suppression fichier/dossier (+ nettoyage des thumbs) |
| `/bninefiles/mkdir/{domain}/{id}` | POST (form `{path, name}`) | `bninefiles_files_mkdir` | `edit` | Création de sous-dossier |
| `/bninefiles/download/{domain}/{id}?path=` | GET | `bninefiles_files_download` | `view` | Téléchargement (attachment) |
| `/bninefiles/image/{domain}/{id}?path=` | GET | `bninefiles_files_image` | `view` | Affichage inline (Content-Disposition: inline) |
| `/bninefiles/thumbnail/{domain}/{id}?path=` | GET | `bninefiles_files_thumbnail` | `view` | Miniature auto-générée (≥300px côté le plus petit) |
| `/bninefiles/gallery/{domain}/{id}/{editable}?select=&path=&compact=` | GET | `bninefiles_files_gallery` | `view`/`edit` | Galerie d'images (grille + lightbox ou modale sélection) |
| `/bninefiles/crop-page/{domain}/{id}?path=&min_size=&ratio=&configurable=` | GET | `bninefiles_files_crop_page` | `edit` | Page de recadrage (iframe modale) |
| `/bninefiles/crop/{domain}/{id}` | POST | `bninefiles_files_crop` | `edit` | Application du crop (génère thumbnail `{size}x{size}`, défaut 150) |

**Notes :**

- `editable` ∈ `{0, 1}`. Si absent, défaut = `1`.
- `path` (query string) = sous-dossier courant dans `{domain}/{id}/`.
- `select` (gallery) ∈ `{'onSelectSingle', 'multiple'}` ou vide (lightbox seule).
- `compact` (list/gallery) ∈ `{0, 1}`. Si `1`, retire les wrappers `card`/`header` pour intégration dans une autre UI.
- `imageOnly` (uploadmodal) ∈ `{0, 1}`. Si `1`, filtre les extensions aux formats image.
- `crop` / `min_size` / `ratio` / `configurable` (uploadmodal, crop-page) : reportés en query string depuis `IconUploadType` (section 9) ou construits manuellement.

---

## 5. Démarrage rapide : un navigateur de fichiers

Afficher un navigateur de fichiers complet (upload, dossiers, suppression, téléchargement) pour une entité, en deux lignes de Twig :

```php
// src/Controller/ArticleController.php
#[Route('/admin/articles/{id}/files', name: 'admin_article_files')]
public function files(Article $article): Response
{
    return $this->render('admin/article_files.html.twig', [
        'domain' => 'article',
        'entity_id' => $article->getId(),
    ]);
}
```

```twig
{# templates/admin/article_files.html.twig #}
{% extends 'admin/base.html.twig' %}

{% block body %}
    <h1>Fichiers attachés à l'article #{{ entity_id }}</h1>

    {# Mode écriture (upload + dossiers + suppression) #}
    {{ render(controller(
        'Bnine\\FilesBundle\\Controller\\FileController::browse',
        { domain: domain, id: entity_id, editable: 1 }
    )) }}
{% endblock %}
```

C'est tout : le navigateur est fonctionnel. Pour passer en lecture seule, mettre `editable: 0`.

Pour une **galerie d'images** avec lightbox :

```twig
{{ render(controller(
    'Bnine\\FilesBundle\\Controller\\FileController::gallery',
    { domain: 'article', id: entity_id, editable: 1 }
)) }}
```

---

## 6. Voter de sécurité

`Bnine\FilesBundle\Security\AbstractFileVoter` gère 3 attributs (`view`, `edit`, `delete`) avec un subject de type `[domain, id]`.

### 6.1 Voter concret

```php
namespace App\Security;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractFileVoter
{
    /** Domaines publics : pas d'auth requise */
    private const PUBLIC_DOMAINS = ['avatar', 'logo', 'icon'];

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        if (in_array($domain, self::PUBLIC_DOMAINS, true)) {
            return true;
        }
        return $this->canManage($domain, $id, $token);
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        return $this->canManage($domain, $id, $token);
    }

    private function canManage(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user) {
            return false;
        }

        // Exemple : un user gère ses propres fichiers
        if ('user-document' === $domain && (int) $id === $user->getId()) {
            return true;
        }

        // Admins gèrent tout
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
```

### 6.2 Déclaration du service

```yaml
# config/services.yaml
App\Security\FileVoter:
    tags: ['security.voter']
```

> **Note** : le voter **doit** être tagué `security.voter` pour être appelé par `denyAccessUnlessGranted`. Sans voter actif, Symfony lève une `AccessDeniedException`.

---

## 7. Système de modale (JS)

`bninefiles.js` expose une modale overlay légère (z-index 9999) qui charge une page dans un iframe.

### 7.1 API

```javascript
// Ouvrir une modale
BnineModalOpen({
    id: 'ma-modale',            // ID unique (string) — obligatoire
    title: 'Titre affiché',     // string
    url: '/bninefiles/uploadmodal/blog/42',  // URL chargée dans l'iframe — obligatoire
    height: '600px',            // Hauteur iframe (défaut : '600px')
    currentInput: 'id_input',   // (optionnel) ID du champ caché à mettre à jour
    onClose: function () {      // Callback appelé à la fermeture
        // ...
    }
});

// Fermer depuis l'iframe (ex: bouton Annuler)
window.parent.BnineModalClose();

// Callback après upload (appelé par le contenu de l'iframe après upload + crop éventuel)
window.parent.imageUploadDone('avatar/0/_thumbs/abc123.jpg', 'http://app/bninefiles/image/avatar/0?path=_thumbs/abc123.jpg');

// Fermer programmatiquement
BnineModalClose();
```

### 7.2 Événements

Un événement global `bnine-modal-closed` est dispatché à la fermeture sur `document`. Utile pour recharger une galerie :

```javascript
document.addEventListener('bnine-modal-closed', function () {
    // Rafraîchir la galerie parente
});
```

---

## 8. Galerie + sélection single/multiple

La galerie peut être ouverte en mode **sélection** depuis n'importe quelle page.

### 8.1 Mode single — sélection immédiate

URL : `/bninefiles/gallery/{domain}/{id}/0?select=onSelectSingle`

Code JS côté page hôte :

```javascript
window.bnineFileSelect = function (data) {
    // data = { url: string, path: string, name: string }
    console.log('Image choisie :', data);
};
```

Le clic sur une image appelle `bnineFileSelect(data)`, puis ferme la modale.

### 8.2 Mode multiple — validation explicite

URL : `/bninefiles/gallery/{domain}/{id}/0?select=multiple`

Un bouton **Valider (N)** apparaît en bas (sticky). Le clic appelle :

```javascript
window.bnineFileSelect = function (items) {
    // items = [ { url, path, name }, ... ]
    console.log('Images choisies :', items);
};
```

### 8.3 Mode normal — lightbox seule

Sans `?select=...`, la galerie est en lecture seule avec lightbox GLightbox (loop + touchNavigation + preload).

### 8.4 Exemple complet

```twig
<a href="#" onclick="openGallery(); return false;">Choisir une image</a>

<div id="result"></div>

<script>
function openGallery() {
    BnineModalOpen({
        id: 'select-gallery',
        title: 'Choisir une image',
        url: '/bninefiles/gallery/blog/42/0?select=onSelectSingle',
        height: '700px'
    });
}

window.bnineFileSelect = function (data) {
    document.getElementById('result').innerHTML =
        '<img src="' + data.url + '" alt=""><br><code>' + data.path + '</code>';
};
</script>
```

> Pour un usage avec persistance du résultat dans un `<input>`, utilisez plutôt `SelectFileType` (section 10) ou le widget `_select.html.twig` (section 11) — ils gèrent le multi-instance et le additif automatiquement.

---

## 9. `IconUploadType` — Form Type

Champ caché qui ouvre une modale d'upload, affiche une preview, supporte le recadrage optionnel.

### 9.1 Options

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `label` | string | — | Label Symfony standard |
| `icon_endpoint` | string | `"icon"` | Domaine logique (utilisé par les hooks applicatifs) |
| `icon_label` | string | `"Icon"` | Titre de la modale d'upload + texte du bouton |
| `icon_empty_preview` | string | `"medias/icon/icon_pin.png"` | URL d'une preview quand l'input est vide |
| `icon_domain` | string | `"avatar"` | **Domaine de stockage** (sous-dossier `uploads/`) |
| `icon_entity_id` | int\|string | `0` | **ID de l'entité propriétaire** |
| `crop` | bool | `false` | Active le recadrage après upload |
| `crop_min_size` | int | `300` | Taille minimale finale (largeur ET hauteur), bornes 50–2000 |
| `crop_ratio` | string | `"1/1"` | Ratio largeur/hauteur (`"1/1"`, `"16/9"`, `"4/3"`, `"free"`, …) |
| `crop_configurable` | bool | `false` | `true` = champs `min_size` et `ratio` éditables dans la modale crop |
| `preview_max_height` | int | `100` | Hauteur max (px) de la preview dans le widget parent |

> `icon_domain` et `icon_entity_id` sont validés en type par `setAllowedTypes` (string / int|string). Le router est injecté via `setRouter()` (autoconfiguré dans `config/services.yaml` du bundle).

### 9.2 Exemple Form Type

```php
use Bnine\FilesBundle\Form\Type\IconUploadType;

$builder
    // Avatar carré 300x300, non configurable, preview 100px
    ->add('avatar', IconUploadType::class, [
        'label'              => 'Avatar',
        'icon_label'         => 'Avatar',
        'icon_empty_preview' => '',
        'icon_domain'        => 'avatar',
        'icon_entity_id'     => $user->getId(),
        'crop'               => true,
        'crop_min_size'      => 300,
        'crop_ratio'         => '1/1',
        'crop_configurable'  => false,
        'preview_max_height' => 100,
    ])
    // Bannière 16/9 configurable, preview 250px
    ->add('banner', IconUploadType::class, [
        'label'              => 'Bannière',
        'icon_label'         => 'Bannière',
        'icon_domain'        => 'avatar',
        'icon_entity_id'     => $user->getId(),
        'crop'               => true,
        'crop_min_size'      => 400,
        'crop_ratio'         => '16/9',
        'crop_configurable'  => true,
        'preview_max_height' => 250,
    ])
    // Upload simple SANS crop : la modale affiche uniquement DropzoneJS,
    // pas de CropperJS. Pour les domaines publics (avatar/logo/icon), un
    // thumb (copie) est créé automatiquement et le path retourné est
    // préfixé par `_thumbs/`.
    ->add('attachment', IconUploadType::class, [
        'label'              => 'Pièce jointe',
        'icon_label'         => 'Pièce jointe',
        'icon_domain'        => 'blog',
        'icon_entity_id'     => $article->getId(),
        'crop'               => false,
        'preview_max_height' => 150,
    ]);
```

### 9.3 Rendu Twig

```twig
{{ form_label(form.avatar) }}
{{ form_widget(form.avatar) }}
{# Le thème générique Symfony rend l'input caché.
   Le JS du bundle (bninefiles.js) ajoute preview + bouton "Modifier" via la classe .icon-input. #}
```

### 9.4 Persistance

Le champ est un `HiddenType`. La valeur stockée est le **chemin relatif** retourné par le bundle :

- Domaines publics (`avatar`, `logo`, `icon`) : `avatar/{id}/_thumbs/{file}.{ext}` (renommage auto + thumb).
- Autres domaines : `{file}.{ext}` ou `{subdir}/{file}.{ext}`.

Pour afficher le fichier : utiliser la fonction Twig `bninefile()` (section 12).

---

## 10. `SelectFileType` — Form Type

Champ caché qui ouvre une **galerie modale** et permet de sélectionner une ou plusieurs images existantes. Idéal pour « choisir une image existante » sans ré-uploader.

### 10.1 Options

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `mode` | string | `"single"` | `"single"` = 1 image (stocke un string), `"multiple"` = N images (stocke un JSON array) |
| `access` | string | `"write"` | `"write"` = modale avec upload/dossier/suppression, `"read"` = modale en navigation seule |
| `layout` | string | `"inline"` | `"inline"` = preview\|boutons, `"column"` = preview/boutons |
| `domain` | string | `"sample"` | Domaine de stockage |
| `entity_id` | int\|string | `1` | ID de l'entité propriétaire |
| `required` | bool | `false` | Ajoute l'attribut HTML `required` sur l'input caché |
| `show_name` | bool | `false` | Affiche le nom du fichier sous chaque vignette |
| `max_height` | int\|null | `100` | Hauteur max de la preview (px) |
| `grid_cols` | int | `4` | Nb colonnes de la grille (mode `multiple` uniquement) |
| `placeholder` | string\|null | auto | Texte affiché quand aucune image n'est sélectionnée |

> Les valeurs `mode`, `access`, `layout` sont validées par `setAllowedValues`. Les types sont validés par `setAllowedTypes` — un mauvais type lève une `InvalidOptionsException` au `buildForm`.

### 10.2 Exemple Form Type

```php
use Bnine\FilesBundle\Form\Type\SelectFileType;

$builder
    // Image de couverture : SelectFileType single
    ->add('image', SelectFileType::class, [
        'label'       => 'Image de couverture',
        'mode'        => 'single',
        'access'      => 'write',
        'layout'      => 'inline',
        'domain'      => 'sample',
        'entity_id'   => 1,
        'required'    => true,
        'show_name'   => true,
        'max_height'  => 150,
    ])
    // Galerie : SelectFileType multiple
    ->add('gallery', SelectFileType::class, [
        'label'       => 'Galerie d\'images',
        'mode'        => 'multiple',
        'access'      => 'write',
        'layout'      => 'inline',
        'domain'      => 'sample',
        'entity_id'   => 1,
        'required'    => false,
        'show_name'   => true,
        'max_height'  => 100,
        'grid_cols'   => 5,
    ]);
```

### 10.3 DTO

```php
use Symfony\Component\Validator\Constraints as Assert;

class ArticleDto
{
    #[Assert\NotBlank(message: 'L\'image est obligatoire.')]
    public ?string $image = null;     // single : chemin unique

    public string $gallery = '[]';    // multiple : JSON array de chemins
}
```

### 10.4 Récupération côté serveur

```php
$form->handleRequest($request);
if ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData();

    // $data->image   : string|null   ex: 'sample/1/01-008.jpg'
    // $data->gallery : string JSON   ex: '["sample/1/a.jpg","sample/1/b.jpg"]'

    $paths = json_decode($data->gallery ?? '[]', true) ?: [];
}
```

### 10.5 Rendu Twig

```twig
{{ form_row(form.image) }}     {# tout-en-un : label + widget + erreurs #}

{# Ou détaillé : #}
{{ form_label(form.image) }}
{{ form_widget(form.image) }}
```

> **Pré-requis** : le theme Twig `@BnineFilesBundle/Form/_theme.html.twig` doit être activé dans `twig.yaml` (cf. section 2.4). Sans ce theme, le widget n'est pas rendu correctement (juste un input caché).

### 10.6 Affichage final

```twig
{# single #}
<img src="{{ bninefile(article.image) }}" alt="">

{# multiple #}
{% for path in paths %}
    <img src="{{ bninefile(path) }}" alt="">
{% endfor %}
```

---

## 11. Widget Twig `@BnineFilesBundle/file/_select.html.twig`

Pour les cas où on veut intégrer le widget **hors Form Type Symfony** (formulaire HTML brut, UI 100% custom), le bundle expose un include Twig.

### 11.1 Options (toutes)

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `mode` | string | `"single"` | `"single"` ou `"multiple"` |
| `access` | string | `"write"` | `"write"` ou `"read"` |
| `layout` | string | `"inline"` | `"inline"` ou `"column"` |
| `input_name` | string | auto (`bninefile[{rand}]`) | Nom HTML de l'input caché |
| `input_id` | string | auto depuis `input_name` + random | ID HTML de l'input caché |
| `domain` | string | `"uploads"` | Domaine de stockage |
| `entity_id` | int\|string | `0` | ID de l'entité propriétaire |
| `value` | string\|array | — | Valeur initiale (string en single, array de strings en multiple) |
| `label` | string | — | Libellé affiché au-dessus |
| `required` | bool | `false` | Ajoute `required` sur l'input caché |
| `show_name` | bool | `false` | Affiche le nom du fichier sous chaque vignette |
| `max_height` | int | `100` | Hauteur max de la preview (px) |
| `grid_cols` | int | `4` | Nb colonnes (mode multiple) |
| `placeholder` | string | auto | Texte du placeholder vide |

### 11.2 Exemples

```twig
{# Single, write, inline #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'single',
    access: 'write',
    layout: 'inline',
    input_name: 'article[image]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.image,
    label: 'Image de couverture',
    required: true,
    max_height: 200,
    show_name: true
} only %}

{# Single, read (lecture seule : modale sans upload) #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'single',
    access: 'read',
    input_name: 'article[preview]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.image,
    label: 'Aperçu'
} only %}

{# Multiple, column, grille 6 colonnes #}
{% include '@BnineFilesBundle/file/_select.html.twig' with {
    mode: 'multiple',
    access: 'write',
    layout: 'column',
    input_name: 'gallery[images]',
    domain: 'avatar',
    entity_id: user.id,
    value: article.galleryImages,   # array de strings ou JSON string
    label: 'Galerie',
    grid_cols: 6,
    show_name: false
} only %}
```

### 11.3 Comportement

- `single` : input contient un chemin relatif (`domain/id/path`)
- `multiple` : input contient un JSON array de strings
- **Additif** : on peut sélectionner en plusieurs fois, sans doublons
- **Auto-fermeture** de la modale après sélection
- **Multi-instance** : chaque widget a son propre `inputId`, pas d'interférence
- **Auto-suffisant** : reconstruit les URLs publiques via `/bninefiles/image/...` (pas besoin de passer les routes)

---

## 12. Extension Twig `bninefile()`

Génère l'URL publique d'un fichier stocké pour un attribut `src` ou `href`.

### 12.1 Usage

```twig
<img src="{{ bninefile(user.avatar) }}" alt="Avatar">
<a href="{{ bninefile(article.document) }}">Télécharger</a>
```

### 12.2 Logique de résolution

| Entrée | Sortie |
|--------|--------|
| `null` / `''` | `''` |
| URL `http(s)://...` | renvoyée telle quelle |
| Chemin `/bninefiles/...` | renvoyé tel quel |
| Chemin `bundles/...` | préfixé par `/` |
| Chemin `uploads/...` (legacy) | préfixé par `/` |
| Format `{domain}/{id}/{path}` | `/bninefiles/image/{domain}/{id}?path={path}` |

### 12.3 Exemple

```twig
{{ bninefile('avatar/7/a1b2c3d4.jpg') }}
{# → /bninefiles/image/avatar/7?path=a1b2c3d4.jpg #}

{{ bninefile('avatar/0/_thumbs/abc.jpg') }}
{# → /bninefiles/image/avatar/0?path=_thumbs%2Fabc.jpg #}
```

---

## 13. Service `FileService`

API publique pour les opérations bas niveau (utilisée par `FileController` mais accessible depuis vos contrôleurs).

### 13.1 Constructeur

```php
public function __construct(KernelInterface $kernel, ?FilesystemOperator $storage = null, bool $s3 = false)
```

### 13.2 Méthodes

```php
use Bnine\FilesBundle\Service\FileService;

// Initialise le dossier de l'entité (idempotent)
$fileService->init(string $domain, string $id): void;

// Crée récursivement un sous-dossier (utile avant upload dans un sous-dossier)
$fileService->ensureDirectory(string $domain, string $id, string $relativePath = ''): void;

// Liste les entrées d'un dossier (exclut les dossiers préfixés par _)
// Retourne : [['name' => string, 'isDirectory' => bool, 'path' => string], ...]
$fileService->list(string $domain, string $id, string $relativePath = ''): array;

// Crée un sous-dossier
$fileService->makeDirectory(string $domain, string $id, string $relativePath, string $name): void;

// Supprime un fichier ou dossier (+ ses thumbs)
$fileService->delete(string $domain, string $id, string $relativePath): void;

// Supprime les thumbs d'un fichier (sans toucher au fichier source)
$fileService->deleteThumbs(string $domain, string $id, string $relativePath): void;

// Helpers chemins
$fileService->getEntityPath(string $domain, string $id): string;           // chemin absolu local
$fileService->getRelativePath(string $domain, string $id, string $filePath): string;  // chemin Flysystem

// Storage
$fileService->isS3(): bool;
$fileService->hasStorage(): bool;
$fileService->getStorage(): ?\League\Flysystem\FilesystemOperator;
```

### 13.3 Exemple : usage dans un contrôleur custom

```php
use Bnine\FilesBundle\Service\FileService;

class ArticleController extends AbstractController
{
    public function uploadToSubfolder(FileService $fs, Request $request, Article $article): Response
    {
        // Crée récursivement uploads/blog/{id}/photos/2024/
        $fs->ensureDirectory('blog', (string) $article->getId(), 'photos/2024');

        $file = $request->files->get('photo');
        $fs->getStorage()->writeStream(
            $fs->getRelativePath('blog', (string) $article->getId(), 'photos/2024/'.$file->getClientOriginalName()),
            fopen($file->getPathname(), 'rb')
        );

        return new JsonResponse(['success' => true]);
    }
}
```

---

## 14. Stockage (local / S3)

### 14.1 Mode local

Arborescence sur disque (`{uploads}` = `kernel.project_dir/uploads` par défaut) :

```
uploads/
└── {domain}/
    └── {id}/
        ├── fichier.jpg
        ├── sous-dossier/
        │   └── autre.png
        └── _thumbs/
            ├── 300xN/
            │   └── fichier.jpg     # miniature auto (mini 300px côté le plus petit)
            └── 150x150/
                └── fichier.jpg     # miniature crop (150x150 par défaut)
```

Les dossiers dont le nom commence par `_` (notamment `_thumbs`) sont exclus de la navigation et des listings.

### 14.2 Mode S3

Tout fonctionne via Flysystem. Le bucket doit être configuré avec une clé d'accès.

Variables :

```env
STORAGE_S3=1
S3_ENDPOINT=http://minio:9000
S3_BUCKET=my-uploads
S3_ACCESS_KEY=changeme
S3_SECRET_KEY=changeme
S3_REGION=us-east-1
```

Le bundle gère :

- Création de dossiers (récursivement, segment par segment)
- Suppression de fichiers / dossiers
- Stream upload (zéro copie locale si S3)
- Listing via `listContents`

### 14.3 Thumbnails

Générés via `imagine/imagine` (avec GD ou Imagick) :

- **Thumbnail auto** : route `bninefiles_files_thumbnail` — redimensionne pour que la plus petite dimension soit ≥ 300px (en conservant le ratio).
- **Thumbnail crop** : route `bninefiles_files_crop` — crop à la sélection utilisateur, redimensionné à la taille demandée (`min_size` par défaut 150).

Cache navigateur `Cache-Control: public, max-age=2592000` (30 jours) sur les routes `image` et `thumbnail`.

---

## 15. Sample de démonstration

Un sample Symfony 7 complet est disponible dans `sample/`. Il démarre via Docker Compose sur le port **9002** :

```bash
cd sample
docker compose up -d
# Ouvrir http://localhost:9002
```

Le sample démontre **toutes les fonctionnalités du bundle** avec, pour chaque page, le code d'usage complet (contrôleur, Form Type, Twig, options) :

| URL | Démontre |
|-----|----------|
| `/` | Menu d'accueil |
| `/browse` | Navigateur de fichiers : upload, dossiers, suppression |
| `/browse?editable=1` | Browse en **mode écriture** |
| `/browse?editable=0` | Browse en **mode lecture** |
| `/gallery` | Galerie avec lightbox GLightbox |
| `/gallery?editable=1` | Galerie en **mode écriture** |
| `/gallery?editable=0` | Galerie en **mode lecture** |
| `/icon` | Icônes HTML avec modale d'upload + recadrage (5 variantes) |
| `/form-icon` | Form Symfony avec `IconUploadType` (5 champs) |
| `/form-demo` | Form Symfony avec `SelectFileType` (image + galerie) |
| `/select-demo-single` | Widget `_select.html.twig` en mode single (3 démos) |
| `/select-demo-multiple` | Widget `_select.html.twig` en mode multiple (3 démos) |

Voir [sample/README.md](sample/README.md) pour les détails Docker.

---

## 16. Dépannage

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
Le theme Twig `@BnineFilesBundle/Form/_theme.html.twig` n'est pas activé dans `config/packages/twig.yaml`. Cf. section 2.4.

**Le crop génère une image floue.**
Augmentez `crop_min_size` (par défaut 300px) ou uploadez une image source plus grande.

---

## 17. API publique (résumé)

| Élément | Type | Description |
|---------|------|-------------|
| `Bnine\FilesBundle\BnineFilesBundle` | class | Active le bundle |
| `Bnine\FilesBundle\Controller\FileController` | class | 11 endpoints HTTP (browse, gallery, upload, download, image, thumbnail, crop, delete, mkdir) |
| `Bnine\FilesBundle\Service\FileService` | class | Logique fichiers (list, upload, suppression, mkdir, ensureDirectory, deleteThumbs) |
| `Bnine\FilesBundle\Security\AbstractFileVoter` | abstract | Voter à implémenter côté hôte (3 méthodes : `canView`, `canEdit`, `canDelete`) |
| `Bnine\FilesBundle\Form\Type\IconUploadType` | form type | Champ avatar/icône avec modale (upload + crop optionnel) |
| `Bnine\FilesBundle\Form\Type\SelectFileType` | form type | Champ sélection image(s) depuis la gallery |
| `Bnine\FilesBundle\Twig\BnineFileExtension` | Twig ext | Fonction `bninefile()` |
| `Bnine\FilesBundle\Cache\CachePolicyInterface` | interface | Stratégie de cache HTTP pour `image()` / `thumbnail()` (voir §18) |
| `Bnine\FilesBundle\Cache\DefaultCachePolicy` | class | Implémentation par défaut (30 jours, public) |
| `@BnineFilesBundle/file/_select.html.twig` | Twig include | Widget standalone (single/multiple, write/read) |
| `@BnineFilesBundle/Form/_theme.html.twig` | Twig form theme | Theme requis par `SelectFileType` |
| `@BnineFilesBundle/partials/_iframe_assets.html.twig` | Twig include | Assets pour iframes standalone (surchargeable par app hôte) |
| `bninefiles.js` | JS | Helpers modal `BnineModalOpen/Close` + widget `IconUploadType` (dispatch `bnine:modal:*`, `bnine:iconupload:change`) |
| `bninefiles-constants.js` | JS | Constantes `window.BnineEvents`, `window.bnineUuid()`, `window.bnineDispatch()` |
| Events JS `bnine:*` | DOM CustomEvent | 13 events (voir §19) : `bnine:modal:open/close/closed`, `bnine:upload:done/error`, `bnine:crop:done/error`, `bnine:select:done`, `bnine:iconupload:change`, `bnine:selectfile:change`, `bnine:browse:delete/mkdir/refresh` |
| Préfixe routes | string | `/bninefiles` (11 routes) |
| Paramètres voter | `[domain, id]` | subject pour `view`/`edit`/`delete` |
| Domaines publics | `['avatar', 'logo', 'icon']` | renommage auto des fichiers uploadés + thumb |
| Modale JS | `BnineModalOpen(opts)` | Helper côté hôte |
| Sélection galerie | `addEventListener('bnine:select:done', cb)` | Callback côté hôte (remplace `window.bnineFileSelect` déprécié) |
| Callback upload | `addEventListener('bnine:upload:done', cb)` | Callback côté hôte (remplace `window.imageUploadDone` déprécié) |

---

## 18. Stratégie de cache HTTP (`CachePolicyInterface`)

Par défaut, les routes `bninefiles_files_image` et `bninefiles_files_thumbnail` répondent avec :

```
Cache-Control: max-age=2592000, public
```

(30 jours, public : le fichier peut être caché par tous les caches — navigateur, CDN, reverse proxy.)

L'application hôte peut surcharger cette politique fichier par fichier en implémentant `Bnine\FilesBundle\Cache\CachePolicyInterface`. Le bundle distingue clairement :

- **`FileVoter`** : décide **QUI** peut accéder au fichier (sécurité)
- **`CachePolicy`** : décide **COMBIEN DE TEMPS** le fichier peut être caché (performance)

### 18.1 Interface `CachePolicyInterface`

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

### 18.2 Enregistrement du service

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

### 18.3 Routes concernées

Le `CachePolicy` est appliqué sur :

- `bninefiles_files_image` (`GET /bninefiles/image/{domain}/{id}?path=...`)
- `bninefiles_files_thumbnail` (`GET /bninefiles/thumbnail/{domain}/{id}?path=...`)

**Pas appliqué** sur `bninefiles_files_download` : un téléchargement ne doit pas être mis en cache public par les proxys.

### 18.4 Granularité

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

### 18.5 Rétrocompatibilité

Si vous ne déclarez aucun `CachePolicyInterface` custom, le bundle utilise `DefaultCachePolicy` qui retourne `null` partout → comportement par défaut inchangé (30 jours, public). Aucun breaking change pour les apps existantes.

---

## 19. CustomEvents JS (extension côté navigateur)

Le bundle dispatche des **CustomEvents DOM standard** à chaque action notable (modale, upload, crop, sélection, suppression). Les apps hotes s'abonnent via `addEventListener` :

```js
document.addEventListener('bnine:upload:done', function (e) {
    console.log('Fichier uploadé :', e.detail.filename);
    // Rafraichir la liste, mettre a jour la BDD, notifier, etc.
});
```

### 19.1 Liste des events

| Event | Quand | `e.detail` |
|-------|-------|------------|
| `bnine:modal:open` | Une modale s'ouvre | `{id, title, url, height, currentInput, origin, endpoint}` |
| `bnine:modal:close` | Une modale commence à se fermer | `{id, origin}` |
| `bnine:modal:closed` | Une modale est complètement fermée | `{id, origin}` |
| `bnine:upload:done` | Un fichier est uploadé avec succès | `{domain, id, path, filename, url, size, mimeType, originalName, origin, endpoint}` |
| `bnine:upload:error` | Erreur d'upload (Dropzone error) | `{message, errors, origin, endpoint}` |
| `bnine:crop:done` | Crop terminé avec succès | `{domain, id, path, url, origin}` |
| `bnine:crop:error` | Erreur de crop | `{message, origin}` |
| `bnine:select:done` | Sélection dans gallery | `{items: [{url, path, name}], mode: 'single'\|'multiple', inputId, origin}` |
| `bnine:iconupload:change` | Valeur d'un IconUploadType change | `{value, inputId, origin}` |
| `bnine:selectfile:change` | Valeur d'un SelectFileType change (y compris clear) | `{value, mode, inputId, origin, cleared?}` |
| `bnine:browse:delete` | Fichier/dossier supprimé | `{path, isDirectory: false, origin}` |
| `bnine:browse:mkdir` | Dossier créé | `{path, name, origin}` |
| `bnine:browse:refresh` | Liste browse/gallery rafraîchie | `{path, origin}` |

### 19.2 `origin` (UUID unique par widget)

Chaque widget (IconUploadType, SelectFileType, gallery) génère un **UUID v4 unique** au moment de son initialisation. Cet UUID est propagé :

- côté JS : `data-instance` sur le DOM, query string `?origin=<uuid>` dans les URLs d'iframe
- côté serveur : passé dans l'event comme `origin`

Cela permet de distinguer 2 galleries sur la même page (rare mais possible).

### 19.3 Exemples d'utilisation

**Logger tous les uploads** (debug) :

```js
document.addEventListener('bnine:upload:done', function (e) {
    console.log('Upload :', e.detail);
});
```

**Rafraîchir une liste après suppression** :

```js
document.addEventListener('bnine:browse:delete', function (e) {
    document.querySelector('#my-list').dataset.lastDeletedPath = e.detail.path;
    // declencher votre propre refresh AJAX
});
```

**Tracker analytics** :

```js
document.addEventListener('bnine:select:done', function (e) {
    if (typeof gtag === 'function') {
        gtag('event', 'file_selected', {
            mode: e.detail.mode,
            count: e.detail.items.length,
            widget: e.detail.origin,
        });
    }
});
```

**Ouvrir une modale custom au lieu du bundle** :

```js
document.addEventListener('bnine:modal:open', function (e) {
    if (e.detail.endpoint === 'iconupload' && myCondition) {
        e.preventDefault(); // annule la modale bundle
        openMyCustomModal();
    }
});
```

### 19.4 BREAKING CHANGE v1.4.5+ : suppression des callbacks globaux

Avant cette version, l'app hôte pouvait utiliser :

```js
// DEPRECIE (supprime)
window.bnineFileSelect = function(data) { /* ... */ };
window.imageUploadDone = function(filepath, fileUrl) { /* ... */ };
```

**Ces callbacks sont supprimés.** Utilisez les CustomEvents `bnine:select:done` et `bnine:upload:done` à la place.

Migration rapide :

```js
// AVANT
window.bnineFileSelect = function(data) {
    console.log(data);
};

// APRES
document.addEventListener('bnine:select:done', function(e) {
    console.log(e.detail.items);
});

// AVANT
window.imageUploadDone = function(filepath, fileUrl) {
    console.log(filepath, fileUrl);
};

// APRES
document.addEventListener('bnine:upload:done', function(e) {
    console.log(e.detail.filename, e.detail.url);
});
```

Les helpers `BnineModalOpen()` et `BnineModalClose()` restent disponibles (utilitaires, pas des callbacks).

### 19.5 Constantes JS

Les noms d'events sont exposés via `window.BnineEvents` (dans `bninefiles-constants.js`) :

```js
document.addEventListener(BnineEvents.UPLOAD_DONE, function(e) {
    // ...
});
```

Helper UUID : `window.bnineUuid()` retourne un UUID v4.

Helper dispatch (utilisé en interne) : `window.bnineDispatch(name, detail, target)` dispatch un CustomEvent avec fallback IE/old Edge.

---

---

## Licence

MIT.