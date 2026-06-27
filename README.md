# bNine-FilesBundle

A Symfony bundle to easily manage and browse files and directories associated with your application entities.  
Includes secure file upload, download, browsing, directory management, and image gallery features, ready to integrate in your Symfony project.

## Features

- Entity-based file & directory management
- Image gallery view with thumbnails and lightbox
- Secure file upload & download (OneupUploader integration)
- Breadcrumb navigation and Bootstrap/FontAwesome ready templates
- Fine-grained access control (voter system)
- Easy integration and configuration
- Extensible and customizable for your own use-case

## Requirements

### PHP/Symfony Bundles

- Symfony 7+
- PHP 8.1+
- [oneup/uploader-bundle](https://github.com/1up-lab/OneupUploaderBundle)
- [imagine/imagine](https://imagine.readthedocs.io/) (for gallery thumbnail generation)

### JavaScript/CSS Libraries

You must include the following libraries in your project for the bundle's frontend to work correctly:

- [Dropzone.js](https://www.dropzone.dev/) (file upload UI)
- [Font Awesome Free](https://fontawesome.com/) (SVG icons)
- [Bootstrap](https://getbootstrap.com/) (UI framework)
- [jQuery](https://jquery.com/) (required for some interactive features)
- [GLightbox](https://biati-digital.github.io/glightbox/) (image lightbox, required for gallery view)

Example with Symfony Webpack Encore:

```js
// app.js
import 'bootstrap';
import '@fortawesome/fontawesome-free';
import 'dropzone';
import 'dropzone/dist/dropzone-bootstrap.css';
import $ from 'jquery';
```

Or include via CDN in your base template:

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@6/dist/dropzone.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone-bootstrap@1/dist/dropzone-bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css">

<script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dropzone@6/dist/dropzone.min.js"></script>
```

## Installation

```bash
composer require afornerot/bnine-filesbundle
```

## Bundle Setup

### 1. Enable the Bundle

If you use Symfony Flex, the bundle is auto-registered.  
Otherwise, add to `config/bundles.php`:

```php
return [
    // ...
    Bnine\FilesBundle\BnineFilesBundle::class => ['all' => true],
];
```

### 2. Configure Routing

Import the bundle routes in `config/routes.yaml`:

```yaml
bninefilesbundle:
    resource: '@BnineFilesBundle/config/routes.yaml'
    prefix: '/bninefiles'
```

### 3. Configure Twig (Optional)

If you want to use the provided Twig extensions or templates, add in `config/packages/twig.yaml`:

```yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/bnine/filesbundle/templates': bNineFiles
```

### 4. Configure File Uploads (OneupUploaderBundle)

You need to install and configure [OneupUploaderBundle](https://github.com/1up-lab/OneupUploaderBundle):

```bash
composer require oneup/uploader-bundle
```

The upload directory **must always be** `%kernel.project_dir%/uploads`.  
Changing the upload path is **not supported**.

Example in `config/packages/oneup_uploader.yaml`:

```yaml
oneup_uploader:
    mappings:
        bninefile:
            frontend: dropzone                        
```

## Usage

### Controller Example: Initializing a File Container

When creating a new entity, you should initialize the file container for the domain and entity ID after persisting the entity.  
This makes the file area ready for uploads and management.


```php
use Bnine\FilesBundle\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project/submit', name: 'app_admin_project_submit')]
public function submit(Request $request, EntityManagerInterface $em, FileService $fileService): Response
{
    $project = new Project();
     $form = $this->createForm(ProjectType::class, $project);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($project);
        $em->flush();

        // Initialize the file container for this project
        $fileService->init('project', $project->getId());

        return $this->redirectToRoute('app_admin_project');
    }

    return $this->render('project/edit.html.twig', [
        'form' => $form,
    ]);
}}
```

### Template Integration

To display the file container for a specific domain and entity, insert the following line in your Twig template:

```twig
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 0})) }}
```

- **editable**: 0 renders the file browser in read-only mode.
- **editable**: 1 renders with upload, create folder, and delete options enabled.
- **compact**: Add `compact: 1` to remove the card wrapper and render just the content.

**Examples:**

```twig
{# Read-only file browser #}
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 0})) }}

{# Editable file browser (allows upload, create folder, delete) #}
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 1})) }}

{# Compact mode (no card wrapper) #}
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 1, compact: 1})) }}
```

### Image Gallery

The bundle provides a gallery view for image files with thumbnails and lightbox support.

**Routes:**

| Route | Method | Description |
|---|---|---|
| `bninefiles_files_gallery` | GET | Gallery grid view (images + directories) |
| `bninefiles_files_image` | GET | Serves an image inline with proper Content-Type |
| `bninefiles_files_thumbnail` | GET | Generates and caches thumbnails (300px wide) |

**Usage:**

```twig
{# Gallery view (read-only) #}
{{ render(path("bninefiles_files_gallery", {domain: 'project', id: project.id, editable: 0})) }}

{# Gallery view (editable) #}
{{ render(path("bninefiles_files_gallery", {domain: 'project', id: project.id, editable: 1})) }}

{# Compact gallery (no card wrapper) #}
{{ render(path("bninefiles_files_gallery", {domain: 'project', id: project.id, editable: 1, compact: 1})) }}
```

**Features:**
- Grid layout with auto-fill columns (min 200px)
- Server-side thumbnails (300px wide, proportional height) cached in `_thumbs/300xN/`
- GLightbox integration for full-size image viewing
- Upload restricted to image files (client-side filter)
- Non-image files listed separately below the gallery grid

**Thumbnail generation:**
- Uses Imagine library for server-side thumbnail creation
- Thumbnails cached in `uploads/{domain}/{id}/_thumbs/300xN/`
- Quality: 85%, EXIF data stripped
- Fallback to original image on generation failure

## Security & Voters

The bundle provides an abstract voter.  
**You must implement your own voter** in your application for fine-grained access control.

Example:

```php
<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\ProjectRepository;
use Bnine\FilesBundle\Security\AbstractFileVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractFileVoter
{
    private ProjectRepository $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    protected function canView(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return true;
    }

    protected function canEdit(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        switch ($domain) {
            case 'project':
                $project = $this->projectRepository->find($id);
                if ($project && $project->getUsers()->contains($user)) {
                    return true;
                }
                break;
        }

        return false;
    }

    protected function canDelete(string $domain, $id, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        switch ($domain) {
            case 'project':
                $project = $this->projectRepository->find($id);
                if ($project && $project->getUsers()->contains($user)) {
                    return true;
                }
                break;
        }

        return false;
    }
}

```

## License

MIT

## Author

[https://github.com/afornerot](https://github.com/afornerot)

## Contributing

Pull requests and issues are welcome!

## Support

Open an issue on [GitHub](https://github.com/afornerot/bNine-FilesBundle/issues) for bugs or feature requests.
