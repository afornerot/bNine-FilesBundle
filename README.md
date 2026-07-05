# bNine-FilesBundle

Bundle Symfony pour la gestion de fichiers : upload, navigateur, galerie, crop, thumbnail, téléchargement.

## Installation

```bash
composer require bnine/filesbundle
```

Dans `config/bundles.php` :
```php
Bnine\FilesBundle\BnineFilesBundle::class => ['all' => true],
```

## Routes

| Route | Méthode | Description |
|-------|---------|-------------|
| `bninefiles_files` | GET | Navigateur de fichiers |
| `bninefiles_files_uploadmodal` | GET | Page d'upload (iframe) |
| `bninefiles_files_uploadfile` | POST | Upload de fichier |
| `bninefiles_files_delete` | POST | Suppression de fichier/dossier |
| `bninefiles_files_mkdir` | POST | Création de dossier |
| `bninefiles_files_download` | GET | Téléchargement de fichier |
| `bninefiles_files_image` | GET | Affichage d'image inline |
| `bninefiles_files_thumbnail` | GET | Thumbnail auto-généré (300px) |
| `bninefiles_files_gallery` | GET | Galerie d'images |
| `bninefiles_files_crop_page` | GET | Page de crop (sélection imgAreaSelect) |
| `bninefiles_files_crop` | POST | Application du crop (génère thumbnail 150x150) |

## Form Type

### IconUploadType

Champ de formulaire pour upload d'image avec preview et crop optionnel.

```php
use Bnine\FilesBundle\Form\Type\IconUploadType;

$builder->add('avatar', IconType::class, [
    'label' => false,
    'required' => false,
    'icon_endpoint' => 'avatar',
    'icon_label' => 'Avatar',
    'icon_upload_url' => '/bninefiles/uploadmodal/avatar/0?path=&crop',
]);
```

Options :
- `icon_endpoint` : domaine pour les routes (défaut: `icon`)
- `icon_label` : label du bouton (défaut: `Icon`)
- `icon_empty_preview` : image par défaut quand vide
- `icon_upload_url` : URL de la modale d'upload. Ajouter `&crop` pour activer le crop.

## Système de modal custom

Le bundle fournit un système de modal léger (pas de dépendance Bootstrap) via `bninefiles.js` :

```javascript
// Ouvrir une modale
BnineModalOpen({
    id: 'ma-modale',           // ID unique
    title: 'Titre',            // Titre affiché
    url: '/path/to/iframe',    // URL chargée dans l'iframe
    height: '600px',           // Hauteur de l'iframe
    onClose: function () {}    // Callback appelé à la fermeture
});

// Fermer la modale (depuis l'iframe)
window.parent.BnineModalClose();
```

## Twig Function

### `bninefile()`

Génère une URL valide pour un fichier stocké (upload ou statique) :

```twig
<img src="{{ bninefile(user.avatar) }}" alt="Avatar">
<img src="{{ bninefile(group.logo) }}" alt="Logo">
```

La fonction ajoute `/` si nécessaire. Compatible avec les anciens paths (`uploads/...`) et les nouvelles URLs bNineFilesBundle.

## CSS

Le fichier `bninefiles.css` fournit le style du système de modal custom (`.bnine-overlay`, `.bnine-dialog`). À inclure dans le template de base :

```twig
<link rel="stylesheet" href="{{ asset('bundles/bninefilesbundle/css/bninefiles.css') }}">
<script src="{{ asset('bundles/bninefilesbundle/js/bninefiles.js') }}"></script>
```

## Stockage

Le bundle supporte le stockage local et S3 via Flysystem. Le `FileService` détecte automatiquement le mode via un paramètre `bool $s3`.

```yaml
# config/services.yaml
Bnine\FilesBundle\Service\FileService:
    arguments:
        $storage: '@app_storage'
        $s3: '%env(bool:STORAGE_S3)%'
```

### Mode local

```env
STORAGE_DSN="local://uploads"
STORAGE_S3=0
```

### Mode S3

```env
STORAGE_DSN="s3://bucket"
STORAGE_S3=1
S3_ENDPOINT="http://rustfs:9000"
S3_BUCKET="ninegate-uploads"
S3_ACCESS_KEY="rustfs"
S3_SECRET_KEY="changeme"
S3_REGION="us-east-1"
```

## Voter

Le bundle fournit `AbstractFileVoter` abstrait. Dans l'app hôte, implémentez un voter concret :

```php
class FileVoter extends AbstractFileVoter
{
    private const PUBLIC_DOMAINS = ['avatar', 'logo', 'icon'];

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        if (in_array($domain, self::PUBLIC_DOMAINS)) {
            return true;
        }
        return $this->canManage($domain, $id, $token);
    }

    // canEdit, canDelete...
}
```

## Structure des fichiers

```
uploads/                          # Racine du stockage
  {domain}/                       # ex: blog, pagewidgetfile
    {id}/                         # ID de l'entité
      {fichier.jpg}               # Fichiers uploadés
      _thumbs/300xN/              # Thumbnails auto-générés
      _thumbs/{size}x{size}/      # Thumbnails de crop
```
