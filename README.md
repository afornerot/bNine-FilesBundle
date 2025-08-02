# bNine-FilesBundle

A Symfony bundle to easily manage and browse files and directories associated with your application entities.  
Includes secure file upload, download, browsing, and directory management features, ready to integrate in your Symfony project.

## Features

- Entity-based file & directory management
- Secure file upload & download (OneupUploader integration)
- Breadcumb navigation and Bootstrap/FontAwesome ready templates
- Fine-grained access control (voter system)
- Easy integration and configuration
- Extensible and customizable for your own use-case

## Requirements

### PHP/Symfony Bundles

- Symfony 7+
- PHP 8.1+
- [oneup/uploader-bundle](https://github.com/1up-lab/OneupUploaderBundle)

### JavaScript/CSS Libraries

You must include the following libraries in your project for the bundle's frontend to work correctly:

- [Dropzone.js](https://www.dropzone.dev/) (file upload UI)
- [Font Awesome Free](https://fontawesome.com/) (SVG icons)
- [Bootstrap](https://getbootstrap.com/) (UI framework)
- [jQuery](https://jquery.com/) (required for some interactive features)

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

**Example:**

```twig
{# Read-only file browser #}
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 0})) }}

{# Editable file browser (allows upload, create folder, delete) #}
{{ render(path("bninefiles_files", {domain: 'project', id: project.id, editable: 1})) }}
```

This is the only integration you need in your templates.  
Just insert this line wherever you want the file manager to appear!

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
