# Service `FileService`

API publique pour les opérations bas niveau (utilisée par `FileController` mais accessible depuis vos contrôleurs).

## Constructeur

```php
public function __construct(KernelInterface $kernel, ?FilesystemOperator $storage = null, bool $s3 = false)
```

## Méthodes

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

## Exemple : usage dans un contrôleur custom

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