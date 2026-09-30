<?php

namespace Bnine\FilesBundle\Controller;

use Bnine\FilesBundle\Cache\CachePolicyInterface;
use Bnine\FilesBundle\Cache\DefaultCachePolicy;
use Bnine\FilesBundle\Security\AbstractFileVoter;
use Bnine\FilesBundle\Service\FileService;
use Imagine\Gd\Imagine;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif'];

    /** Valeur par defaut du max-age si aucun CachePolicy custom ne la precise. */
    private const DEFAULT_CACHE_MAX_AGE = 86400 * 30; // 30 jours

    private FileService $fileService;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $router;
    private CachePolicyInterface $cachePolicy;

    public function __construct(
        FileService $fileService,
        ?LoggerInterface $logger = null,
        ?UrlGeneratorInterface $router = null,
        ?CachePolicyInterface $cachePolicy = null,
    ) {
        $this->fileService = $fileService;
        $this->logger = $logger ?? new NullLogger();
        $this->router = $router;
        $this->cachePolicy = $cachePolicy ?? new DefaultCachePolicy();
    }

    #[Route('/list/{domain}/{id}/{editable}', name: 'bninefiles_files', methods: ['GET'])]
    public function browse(string $domain, int $id, int $editable, Request $request): Response
    {
        $this->denyAccessUnlessGranted($editable ? AbstractFileVoter::EDIT : AbstractFileVoter::VIEW, [$domain, $id]);

        $relativePath = $request->query->get('path', '');
        $compact = $request->query->has('compact');

        try {
            $files = $this->fileService->list($domain, (string) $id, $relativePath);

            return $this->render('@BnineFiles/file/browse.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => $files,
                'path' => $relativePath,
                'editable' => $editable,
                'compact' => $compact,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('bNine-FilesBundle browse error', [
                'domain' => $domain,
                'id' => $id,
                'path' => $relativePath,
                'exception' => $e,
            ]);
            $this->addFlash('danger', 'Erreur lors de la lecture du dossier : '.$e->getMessage());

            return $this->render('@BnineFiles/file/browse.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => [],
                'path' => '',
                'editable' => $editable,
                'compact' => $compact,
            ]);
        }
    }

    #[Route('/uploadmodal/{domain}/{id}', name: 'bninefiles_files_uploadmodal', methods: ['GET'])]
    public function uploadmodal(string $domain, int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $relativePath = $request->query->get('path', '');
        $imageOnly = $request->query->has('imageOnly');
        $crop = $request->query->has('crop');
        // Options crop (transmises au template crop) :
        //   - min_size (défaut 300, bornes 50-2000) : taille minimale finale (largeur ET hauteur)
        //   - ratio (défaut 1/1, ou "free") : ratio largeur/hauteur
        //   - configurable (défaut false) : si true, l'user peut modifier min_size et ratio
        $minSize = max(50, min(2000, (int) ($request->query->get('min_size') ?? 300)));
        $ratio = trim((string) ($request->query->get('ratio') ?? '1/1'));
        if ('' === $ratio) {
            $ratio = '1/1';
        }
        $configurable = filter_var($request->query->get('configurable', '0'), \FILTER_VALIDATE_BOOLEAN);

        return $this->render('@BnineFiles\file\upload.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar'=> false,
            'endpoint'  => 'bninefile',
            'domain'    => $domain,
            'id'        => $id,
            'path'      => $relativePath,
            'imageOnly' => $imageOnly,
            'crop'      => $crop,
            'minSize'   => $minSize,
            'ratio'     => $ratio,
            'configurable'    => $configurable,
        ]);
    }

    #[Route('/uploadfile', name: 'bninefiles_files_uploadfile', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('bninefile');
        $domain = $request->query->get('domain');
        $id = $request->query->get('id');
        $relativePath = $request->query->get('path', '');

        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        if (!$file || !$domain || null === $id || '' === $id) {
            return new JsonResponse(['error' => 'Paramètres invalides.'], 400);
        }

        try {
            $originalName = $file->getClientOriginalName();
            if ('' === $originalName) {
                return new JsonResponse(['error' => 'Nom de fichier invalide.'], 400);
            }

            $publicDomains = ['avatar', 'logo', 'icon'];
            $isPublicDomain = in_array($domain, $publicDomains, true);
            if ($isPublicDomain) {
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $originalName = bin2hex(random_bytes(16)).($ext ? '.'.$ext : '');
            }

            // Calcule le path du thumb avant de move le fichier (le fichier temp disparaît après move).
            $thumbRelativePath = null;
            $thumbDir = $relativePath
                ? trim($relativePath, '/').'/_thumbs'
                : '_thumbs';
            $thumbDirForSave = $thumbRelativePath = trim($thumbDir.'/'.$originalName, '/');

            if ($this->fileService->isS3()) {
                $this->fileService->ensureDirectory($domain, $id, $relativePath);
                $dirPath = $this->fileService->getRelativePath($domain, (string) $id, $relativePath);
                $storagePath = rtrim($dirPath, '/').'/'.$originalName;

                $this->fileService->deleteThumbs($domain, $id, $relativePath.'/'.$originalName);

                $this->fileService->getStorage()->writeStream(
                    $storagePath,
                    fopen($file->getPathname(), 'rb')
                );

                if ($isPublicDomain) {
                    // Copie le thumb (S3)
                    $thumbStoragePath = $this->fileService->getRelativePath($domain, (string) $id, $thumbRelativePath);
                    $this->fileService->getStorage()->createDirectory(
                        $this->fileService->getRelativePath($domain, (string) $id, $thumbDir)
                    );
                    $this->fileService->getStorage()->writeStream(
                        $thumbStoragePath,
                        fopen($file->getPathname(), 'rb')
                    );
                }
            } else {
                $this->fileService->ensureDirectory($domain, $id, $relativePath);

                $baseDir = $this->getParameter('kernel.project_dir').'/uploads/'.$domain.'/'.$id.'/'.ltrim($relativePath, '/');

                $this->fileService->deleteThumbs($domain, $id, $relativePath.'/'.$originalName);

                $file->move($baseDir, $originalName);

                if ($isPublicDomain) {
                    // Copie le thumb (local) : le fichier source vient d'être move, on l'utilise depuis sa destination.
                    $thumbBaseDir = $baseDir.'/_thumbs';
                    if (!is_dir($thumbBaseDir)) {
                        @mkdir($thumbBaseDir, 0775, true);
                    }
                    copy($baseDir.'/'.$originalName, $thumbBaseDir.'/'.$originalName);
                }
            }

            // On renvoie TOUJOURS un chemin préfixé par 'domain/id/...'
            // afin que le widget parent (et la fonction Twig bninefile()) puisse
            // reconstruire l'URL publique via /bninefiles/image/{domain}/{id}?path=...
            // - Domaine public : on renvoie le thumb (préfixé _thumbs/) → 'avatar/0/_thumbs/xxx.jpg'
            // - Domaine non-public : on renvoie le fichier uploadé → 'sample/1/foo.jpg'
            if ($isPublicDomain) {
                $returnedPath = $domain.'/'.$id.'/'.$thumbRelativePath;
            } else {
                $base = '' === $relativePath ? '' : trim($relativePath, '/').'/';
                $returnedPath = $domain.'/'.$id.'/'.$base.$originalName;
            }

            return new JsonResponse(['success' => true, 'filename' => $returnedPath]);
        } catch (\Throwable $e) {
            $this->logger->error('bNine-FilesBundle upload error', ['exception' => $e]);

            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/delete/{domain}/{id}', name: 'bninefiles_files_delete', methods: ['POST'])]
    public function delete(string $domain, int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::DELETE, [$domain, $id]);

        $data = json_decode($request->getContent(), true);
        $relativePath = $data['path'] ?? null;

        if (!$relativePath) {
            return $this->json(['error' => 'Chemin non fourni.'], 400);
        }

        try {
            $this->fileService->delete($domain, (string) $id, $relativePath);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/mkdir/{domain}/{id}', name: 'bninefiles_files_mkdir', methods: ['POST'])]
    public function mkdir(string $domain, int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $path = $request->request->get('path');
        $name = $request->request->get('name');

        if (!$name || !is_string($name)) {
            return $this->json(['error' => 'Nom de dossier manquant.'], 400);
        }

        try {
            $this->fileService->makeDirectory($domain, (string) $id, (string) $path, $name);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/download/{domain}/{id}', name: 'bninefiles_files_download', methods: ['GET'])]
    public function download(Request $request, string $domain, int $id): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::VIEW, [$domain, $id]);

        $filePath = $request->query->get('path');

        if (!$filePath) {
            throw $this->createNotFoundException('Fichier non spécifié.');
        }

        $filename = basename($filePath);

        if ($this->fileService->isS3()) {
            $storagePath = $this->fileService->getRelativePath($domain, (string) $id, $filePath);

            if (!$this->fileService->getStorage()->fileExists($storagePath)) {
                throw $this->createNotFoundException('Fichier introuvable.');
            }

            $stream = $this->fileService->getStorage()->readStream($storagePath);
            $mimeType = $this->fileService->getStorage()->mimeType($storagePath);

            $response = new StreamedResponse(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            });

            $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));
            $response->headers->set('Content-Length', $this->fileService->getStorage()->fileSize($storagePath));
            $response->setMaxAge(86400 * 30);

            return $response;
        }

        $basePath = $this->fileService->getEntityPath($domain, (string) $id);
        $absolutePath = realpath($basePath.'/'.$filePath);

        if (!$absolutePath || !str_starts_with($absolutePath, $basePath)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    #[Route('/gallery/{domain}/{id}/{editable}', name: 'bninefiles_files_gallery', methods: ['GET'])]
    public function gallery(string $domain, int $id, int $editable, Request $request): Response
    {
        $this->denyAccessUnlessGranted($editable ? AbstractFileVoter::EDIT : AbstractFileVoter::VIEW, [$domain, $id]);

        $relativePath = $request->query->get('path', '');
        $compact = $request->query->has('compact');
        $select = $request->query->get('select', '');

        try {
            $allFiles = $this->fileService->list($domain, (string) $id, $relativePath);

            $files = array_filter($allFiles, function ($file) {
                if ($file['isDirectory']) {
                    return true;
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                return in_array($ext, self::IMAGE_EXTENSIONS);
            });

            return $this->render('@BnineFiles/file/gallery.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => array_values($files),
                'allFiles' => $allFiles,
                'path' => $relativePath,
                'editable' => $editable,
                'compact' => $compact,
                'select' => $select,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('bNine-FilesBundle gallery error', [
                'domain' => $domain,
                'id' => $id,
                'path' => $relativePath,
                'exception' => $e,
            ]);
            $this->addFlash('danger', 'Erreur lors de la lecture de la galerie : '.$e->getMessage());

            return $this->render('@BnineFiles/file/gallery.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => [],
                'allFiles' => [],
                'path' => '',
                'editable' => $editable,
                'compact' => $compact,
                'select' => $select,
            ]);
        }
    }

    #[Route('/image/{domain}/{id}', name: 'bninefiles_files_image', methods: ['GET'])]
    public function image(Request $request, string $domain, int $id): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::VIEW, [$domain, $id]);

        $filePath = $request->query->get('path');

        if (!$filePath) {
            throw $this->createNotFoundException('Fichier non spécifié.');
        }

        if ($this->fileService->isS3()) {
            $storagePath = $this->fileService->getRelativePath($domain, (string) $id, $filePath);

            if (!$this->fileService->getStorage()->fileExists($storagePath)) {
                throw $this->createNotFoundException('Fichier introuvable.');
            }

            $stream = $this->fileService->getStorage()->readStream($storagePath);
            $mimeType = $this->fileService->getStorage()->mimeType($storagePath);

            $response = new StreamedResponse(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            });

            $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
            $response->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_INLINE);
            $this->applyCachePolicy($response, $domain, (string) $id, $filePath);

            return $response;
        }

        $basePath = $this->fileService->getEntityPath($domain, (string) $id);
        $absolutePath = realpath($basePath.'/'.$filePath);

        if (!$absolutePath || !str_starts_with($absolutePath, $basePath)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        $mimeType = (new MimeTypes())->guessMimeType($absolutePath);
        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        $this->applyCachePolicy($response, $domain, (string) $id, $filePath);

        return $response;
    }

    #[Route('/thumbnail/{domain}/{id}', name: 'bninefiles_files_thumbnail', methods: ['GET'])]
    public function thumbnail(Request $request, string $domain, int $id): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::VIEW, [$domain, $id]);

        $filePath = $request->query->get('path');
        $minSize = 300;

        if (!$filePath) {
            throw $this->createNotFoundException('Fichier non spécifié.');
        }

        $thumbSubPath = '_thumbs/'.$minSize.'xN';
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $thumbFilename = $filename.'.'.$ext;
        $thumbRelativePath = $thumbSubPath.'/'.$thumbFilename;

        if ($this->fileService->isS3()) {
            $storagePath = $this->fileService->getRelativePath($domain, (string) $id, $filePath);
            $thumbStoragePath = $this->fileService->getRelativePath($domain, (string) $id, $thumbRelativePath);

            if (!$this->fileService->getStorage()->fileExists($storagePath)) {
                throw $this->createNotFoundException('Fichier introuvable.');
            }

            if (!$this->fileService->getStorage()->fileExists($thumbStoragePath)) {
                try {
                    $tmpDir = sys_get_temp_dir().'/ninegate_thumbs';
                    if (!is_dir($tmpDir)) {
                        mkdir($tmpDir, 0775, true);
                    }

                    $tmpSource = tempnam($tmpDir, 'src_').'.'.$ext;
                    $tmpThumb = tempnam($tmpDir, 'thumb_').'.'.$ext;

                    $stream = $this->fileService->getStorage()->readStream($storagePath);
                    file_put_contents($tmpSource, stream_get_contents($stream));
                    fclose($stream);

                    $imagine = new Imagine();
                    $image = $imagine->open($tmpSource);
                    $size = $image->getSize();

                    $minDim = min($size->getWidth(), $size->getHeight());
                    $ratio = $minSize / $minDim;
                    $thumbWidth = (int) ($size->getWidth() * $ratio);
                    $height = (int) ($size->getHeight() * $ratio);

                    $image->resize(new \Imagine\Image\Box($thumbWidth, $height))
                        ->strip()
                        ->save($tmpThumb, ['quality' => 85]);

                    $this->fileService->getStorage()->writeStream(
                        $thumbStoragePath,
                        fopen($tmpThumb, 'rb')
                    );

                    @unlink($tmpSource);
                    @unlink($tmpThumb);
                } catch (\Exception $e) {
                    $stream = $this->fileService->getStorage()->readStream($storagePath);
                    $mimeType = $this->fileService->getStorage()->mimeType($storagePath);

                    $response = new StreamedResponse(function () use ($stream) {
                        fpassthru($stream);
                        fclose($stream);
                    });

                    $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
                    $response->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_INLINE);

                    return $response;
                }
            }

            $stream = $this->fileService->getStorage()->readStream($thumbStoragePath);
            $mimeType = $this->fileService->getStorage()->mimeType($thumbStoragePath);

            $response = new StreamedResponse(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            });

            $response->headers->set('Content-Type', $mimeType ?: 'application/octet-stream');
            $response->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_INLINE);
            $this->applyCachePolicy($response, $domain, (string) $id, $filePath);

            return $response;
        }

        $basePath = $this->fileService->getEntityPath($domain, (string) $id);
        $absolutePath = realpath($basePath.'/'.$filePath);

        if (!$absolutePath || !str_starts_with($absolutePath, $basePath)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $thumbDir = $basePath.'/_thumbs/'.$minSize.'xN';
        $thumbPath = $thumbDir.'/'.$thumbFilename;

        if (!file_exists($thumbPath)) {
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0775, true);
            }

            try {
                $imagine = new Imagine();
                $image = $imagine->open($absolutePath);
                $size = $image->getSize();

                $minDim = min($size->getWidth(), $size->getHeight());
                $ratio = $minSize / $minDim;
                $thumbWidth = (int) ($size->getWidth() * $ratio);
                $height = (int) ($size->getHeight() * $ratio);

                $image->resize(new \Imagine\Image\Box($thumbWidth, $height))
                    ->strip()
                    ->save($thumbPath, ['quality' => 85]);
            } catch (\Exception $e) {
                $response = new BinaryFileResponse($absolutePath);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

                $mimeType = (new MimeTypes())->guessMimeType($absolutePath);
                if ($mimeType) {
                    $response->headers->set('Content-Type', $mimeType);
                }

                return $response;
            }
        }

        $response = new BinaryFileResponse($thumbPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        $mimeType = (new MimeTypes())->guessMimeType($thumbPath);
        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        $this->applyCachePolicy($response, $domain, (string) $id, $filePath);

        return $response;
    }

    #[Route('/crop-page/{domain}/{id}', name: 'bninefiles_files_crop_page', methods: ['GET'])]
    public function cropPage(Request $request, string $domain, int $id): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::VIEW, [$domain, $id]);

        $filePath = $request->query->get('path');

        if (!$filePath) {
            throw $this->createNotFoundException('Fichier non spécifié.');
        }

        // Options crop :
        //   - min_size (défaut 300, bornes 50-2000) : taille minimale finale (largeur ET hauteur)
        //   - ratio (défaut 1/1, ou "free") : ratio largeur/hauteur
        //   - configurable (défaut false) : si true, l'user peut modifier min_size et ratio
        $minSize = max(50, min(2000, (int) ($request->query->get('min_size') ?? 300)));
        $ratio = trim((string) ($request->query->get('ratio') ?? '1/1'));
        if ('' === $ratio) {
            $ratio = '1/1';
        }
        $configurable = filter_var($request->query->get('configurable', '0'), \FILTER_VALIDATE_BOOLEAN);

        $image = $this->router->generate('bninefiles_files_image', [
            'domain' => $domain,
            'id' => $id,
        ]) . '?path=' . $filePath;

        return $this->render('@BnineFiles/file/crop.html.twig', [
            'domain' => $domain,
            'id' => $id,
            'filePath' => $filePath,
            'image' => $image,
            'minSize' => $minSize,
            'ratio' => $ratio,
            'configurable' => $configurable,
        ]);
    }

    #[Route('/crop/{domain}/{id}', name: 'bninefiles_files_crop', methods: ['POST'])]
    public function crop(Request $request, string $domain, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $filePath = $request->request->get('path');
        $x1 = (int) $request->request->get('x1');
        $y1 = (int) $request->request->get('y1');
        $w = (int) $request->request->get('w');
        $h = (int) $request->request->get('h');

        // min_size : dimension minimale finale (largeur ET hauteur >= min_size).
        // Si le crop donne une image plus petite, on l'agrandit proportionnellement.
        // Défaut : 300. Bornes : [50, 2000].
        $minSize = (int) ($request->request->get('min_size') ?? 300);
        if ($minSize < 50 || $minSize > 2000) {
            return new JsonResponse(['error' => 'min_size doit être entre 50 et 2000.'], 400);
        }

        // ratio : ratio largeur/hauteur optionnel (format libre : "16/9", "4:3", "free", etc.).
        // Si fourni, on l'utilise comme aspectRatio de Cropper (côté UI).
        // Côté backend, on ne contraint pas : le crop sélectionné par l'user fait foi.
        // Valeurs spéciales acceptées : "free" ou ratio libre "w/h".
        $ratio = trim((string) ($request->request->get('ratio') ?? '1/1'));
        if ($ratio !== '' && $ratio !== 'free' && !preg_match('/^(\d+(?:\.\d+)?)\s*[:\/]\s*(\d+(?:\.\d+)?)$/', $ratio)) {
            return new JsonResponse(['error' => 'Ratio invalide (attendu: 16/9, 4/3, 1/1, free, etc.).'], 400);
        }

        if (!$filePath || $w <= 0 || $h <= 0) {
            return new JsonResponse(['error' => 'Paramètres invalides.'], 400);
        }

        // Le thumb est stocké sous _thumbs/ + chemin-complet-non-thumb.
        // Exemple : avatar/0/photo.jpg → _thumbs/avatar/0/photo.jpg
        //          photos/2024/vacances.jpg → _thumbs/photos/2024/vacances.jpg
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileDir = trim(dirname($filePath), '/.');
        $thumbRelativePath = '' === $fileDir
            ? '_thumbs/'.$filename.'.'.$ext
            : '_thumbs/'.$fileDir.'/'.$filename.'.'.$ext;

        // Helper local : calcule le redimensionnement si l'image croppée est trop petite
        $computeFinalSize = function ($cw, $ch) use ($minSize) {
            // Si les deux dimensions sont déjà >= minSize, on garde tel quel
            if ($cw >= $minSize && $ch >= $minSize) {
                return [$cw, $ch];
            }
            // Sinon, on agrandit pour que la plus petite dimension atteigne minSize
            $scale = $minSize / min($cw, $ch);
            return [(int) ceil($cw * $scale), (int) ceil($ch * $scale)];
        };

        if ($this->fileService->isS3()) {
            $storagePath = $this->fileService->getRelativePath($domain, (string) $id, $filePath);
            $thumbStoragePath = $this->fileService->getRelativePath($domain, (string) $id, $thumbRelativePath);

            $tmpDir = sys_get_temp_dir().'/ninegate_crop';
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0775, true);
            }

            $tmpSource = tempnam($tmpDir, 'src_').'.'.$ext;
            $tmpThumb = tempnam($tmpDir, 'crop_').'.'.$ext;

            $stream = $this->fileService->getStorage()->readStream($storagePath);
            file_put_contents($tmpSource, stream_get_contents($stream));
            fclose($stream);

            $imagine = new Imagine();
            $image = $imagine->open($tmpSource);
            $cropBox = new \Imagine\Image\Box($w, $h);
            $cropStart = new \Imagine\Image\Point($x1, $y1);

            // Crop avec taille exacte sélectionnée
            $image->crop($cropStart, $cropBox)->strip();

            // Agrandir si < min_size (en conservant le ratio du crop)
            [$finalW, $finalH] = $computeFinalSize($w, $h);
            $image->resize(new \Imagine\Image\Box($finalW, $finalH))
                ->strip()
                ->save($tmpThumb, ['quality' => 85]);

            $this->fileService->getStorage()->createDirectory(
                $this->fileService->getRelativePath($domain, (string) $id, dirname($thumbRelativePath))
            );
            $this->fileService->getStorage()->writeStream($thumbStoragePath, fopen($tmpThumb, 'rb'));

            @unlink($tmpSource);
            @unlink($tmpThumb);
        } else {
            $basePath = $this->fileService->getEntityPath($domain, (string) $id);
            $absolutePath = realpath($basePath.'/'.$filePath);

            if (!$absolutePath || !str_starts_with($absolutePath, $basePath)) {
                throw $this->createAccessDeniedException('Accès refusé.');
            }

            $thumbDir = $basePath.'/'.dirname($thumbRelativePath);
            if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0775, true) && !is_dir($thumbDir)) {
                throw new \RuntimeException(sprintf('Impossible de créer le répertoire de thumb %s', $thumbDir));
            }
            $thumbPath = $basePath.'/'.$thumbRelativePath;

            $imagine = new Imagine();
            $image = $imagine->open($absolutePath);
            $cropBox = new \Imagine\Image\Box($w, $h);
            $cropStart = new \Imagine\Image\Point($x1, $y1);

            // Crop avec taille exacte sélectionnée
            $image->crop($cropStart, $cropBox)->strip();

            // Agrandir si < min_size (en conservant le ratio du crop)
            [$finalW, $finalH] = $computeFinalSize($w, $h);
            $image->resize(new \Imagine\Image\Box($finalW, $finalH))
                ->strip()
                ->save($thumbPath, ['quality' => 85]);
        }

        // Le path renvoyé est TOUJOURS préfixé par 'domain/id/' afin que le
        // widget parent (et la fonction Twig bninefile()) puisse reconstruire
        // l'URL publique via /bninefiles/image/{domain}/{id}?path=...
        // Exemple : 'avatar/0/_thumbs/foo.jpg'
        $thumbReturnedPath = $domain.'/'.$id.'/'.$thumbRelativePath;

        return new JsonResponse([
            'success' => true,
            'path' => $thumbReturnedPath,
            'url' => $this->router->generate('bninefiles_files_image', [
                'domain' => $domain,
                'id' => $id,
            ]) . '?path=' . rawurlencode($thumbRelativePath),
        ]);
    }

    /**
     * Applique la strategie de cache HTTP a une reponse.
     *
     * Interroge CachePolicyInterface pour :
     *   - la duree du cache (max-age)
     *   - le caractere public/private
     *
     * Si le CachePolicy retourne null pour une option, le bundle utilise
     * sa valeur par defaut (30 jours, public).
     *
     * Si max-age = 0, aucun header Cache-Control n'est ajoute (le
     * navigateur appliquera son propre cache heuristique).
     */
    private function applyCachePolicy(Response $response, string $domain, string $id, string $filePath): void
    {
        $maxAge = $this->cachePolicy->getCacheMaxAge($domain, $id, $filePath);
        $maxAge = $maxAge ?? self::DEFAULT_CACHE_MAX_AGE;

        if ($maxAge > 0) {
            $response->setMaxAge($maxAge);
        }

        $isPublic = $this->cachePolicy->isPublicCacheable($domain, $id, $filePath);
        $isPublic = $isPublic ?? true;

        if ($isPublic) {
            $response->setPublic();
        } else {
            $response->setPrivate();
        }
    }
}
