<?php

namespace Bnine\FilesBundle\Controller;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Bnine\FilesBundle\Service\FileService;
use Imagine\Gd\Imagine;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif'];

    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    #[Route('/list/{domain}/{id}/{editable}', name: 'bninefiles_files', methods: ['GET'])]
    public function browse(string $domain, int $id, int $editable, Request $request): Response
    {
        $this->denyAccessUnlessGranted($editable ? AbstractFileVoter::EDIT : AbstractFileVoter::VIEW, [$domain, $id]);

        $relativePath = $request->query->get('path', '');
        $compact = $request->query->has('compact');

        try {
            $files = $this->fileService->list($domain, (string) $id, $relativePath);

            return $this->render('@BnineFilesBundle/file/browse.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => $files,
                'path' => $relativePath,
                'editable' => $editable,
                'compact' => $compact,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
            dd($e->getMessage());

            return $this->redirectToRoute('bninefiles_files', [
                'domain' => $domain,
                'id' => $id,
                'editable' => $editable,
            ]);
        }
    }

    #[Route('/uploadmodal/{domain}/{id}', name: 'bninefiles_files_uploadmodal', methods: ['GET'])]
    public function uploadmodal(string $domain, int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $relativePath = $request->query->get('path', '');
        $imageOnly = $request->query->has('imageOnly');

        return $this->render('@BnineFilesBundle\file\upload.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar'=> false,
            'endpoint'  => 'bninefile',
            'domain'    => $domain,
            'id'        => $id,
            'path'      => $relativePath,
            'imageOnly' => $imageOnly,
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

        if (!$file || !$domain || !$id) {
            return new JsonResponse('Invalid parameters', 400);
        }

        $originalName = $file->getClientOriginalName();

        if ($this->fileService->isS3()) {
            $dirPath = $this->fileService->getRelativePath($domain, $id, $relativePath);
            $this->fileService->getStorage()->createDirectory($dirPath);

            $storagePath = rtrim($dirPath, '/').'/'.$originalName;

            $this->fileService->getStorage()->writeStream(
                $storagePath,
                fopen($file->getPathname(), 'rb')
            );
        } else {
            $baseDir = $this->getParameter('kernel.project_dir').'/uploads/'.$domain.'/'.$id.'/'.ltrim($relativePath, '/');

            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0775, true);
            }

            $file->move($baseDir, $originalName);
        }

        return new JsonResponse(['success' => true]);
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

        if (!$name) {
            return $this->json(['error' => 'Chemin ou nom manquant.'], 400);
        }

        try {
            $this->fileService->makeDirectory($domain, (string) $id, $path, $name);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
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
            $response->setMaxAge(86400);

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

        try {
            $allFiles = $this->fileService->list($domain, (string) $id, $relativePath);

            $files = array_filter($allFiles, function ($file) {
                if ($file['isDirectory']) {
                    return true;
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                return in_array($ext, self::IMAGE_EXTENSIONS);
            });

            return $this->render('@BnineFilesBundle/file/gallery.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => array_values($files),
                'allFiles' => $allFiles,
                'path' => $relativePath,
                'editable' => $editable,
                'compact' => $compact,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('bninefiles_files_gallery', [
                'domain' => $domain,
                'id' => $id,
                'editable' => $editable,
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
            $response->setMaxAge(86400);

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

        $response->setMaxAge(86400);

        return $response;
    }

    #[Route('/thumbnail/{domain}/{id}', name: 'bninefiles_files_thumbnail', methods: ['GET'])]
    public function thumbnail(Request $request, string $domain, int $id): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::VIEW, [$domain, $id]);

        $filePath = $request->query->get('path');
        $width = 300;

        if (!$filePath) {
            throw $this->createNotFoundException('Fichier non spécifié.');
        }

        $thumbSubPath = '_thumbs/'.$width.'xN';
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

                    $tmpSource = tempnam($tmpDir, 'src_');
                    $tmpThumb = tempnam($tmpDir, 'thumb_');

                    $stream = $this->fileService->getStorage()->readStream($storagePath);
                    file_put_contents($tmpSource, stream_get_contents($stream));
                    fclose($stream);

                    $imagine = new Imagine();
                    $image = $imagine->open($tmpSource);
                    $size = $image->getSize();
                    $ratio = $width / $size->getWidth();
                    $height = (int) ($size->getHeight() * $ratio);

                    $image->resize(new \Imagine\Image\Box($width, $height))
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

        $thumbDir = $basePath.'/_thumbs/'.$width.'xN';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0775, true);
        }

        $thumbPath = $thumbDir.'/'.$thumbFilename;

        if (!file_exists($thumbPath)) {
            try {
                $imagine = new Imagine();
                $image = $imagine->open($absolutePath);
                $size = $image->getSize();
                $ratio = $width / $size->getWidth();
                $height = (int) ($size->getHeight() * $ratio);

                $image->resize(new \Imagine\Image\Box($width, $height))
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

        $response->setMaxAge(86400 * 30);

        return $response;
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
        $size = (int) ($request->request->get('size') ?? 150);

        if (!$filePath || $w <= 0 || $h <= 0) {
            return new JsonResponse(['error' => 'Paramètres invalides.'], 400);
        }

        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $thumbSubPath = '_thumbs/'.$size.'x'.$size;
        $thumbFilename = $filename.'.'.$ext;
        $thumbRelativePath = $thumbSubPath.'/'.$thumbFilename;

        if ($this->fileService->isS3()) {
            $storagePath = $this->fileService->getRelativePath($domain, (string) $id, $filePath);
            $thumbStoragePath = $this->fileService->getRelativePath($domain, (string) $id, $thumbRelativePath);

            $tmpDir = sys_get_temp_dir().'/ninegate_crop';
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0775, true);
            }

            $tmpSource = tempnam($tmpDir, 'src_');
            $tmpThumb = tempnam($tmpDir, 'crop_');

            $stream = $this->fileService->getStorage()->readStream($storagePath);
            file_put_contents($tmpSource, stream_get_contents($stream));
            fclose($stream);

            $imagine = new Imagine();
            $image = $imagine->open($tmpSource);
            $cropBox = new \Imagine\Image\Box($w, $h);
            $cropStart = new \Imagine\Image\Point($x1, $y1);

            $image->crop($cropStart, $cropBox)
                ->resize(new \Imagine\Image\Box($size, $size))
                ->strip()
                ->save($tmpThumb, ['quality' => 85]);

            $this->fileService->getStorage()->createDirectory(
                $this->fileService->getRelativePath($domain, (string) $id, $thumbSubPath)
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

            $thumbDir = $basePath.'/'.$thumbSubPath;
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0775, true);
            }
            $thumbPath = $thumbDir.'/'.$thumbFilename;

            $imagine = new Imagine();
            $image = $imagine->open($absolutePath);
            $cropBox = new \Imagine\Image\Box($w, $h);
            $cropStart = new \Imagine\Image\Point($x1, $y1);

            $image->crop($cropStart, $cropBox)
                ->resize(new \Imagine\Image\Box($size, $size))
                ->strip()
                ->save($thumbPath, ['quality' => 85]);
        }

        return new JsonResponse([
            'success' => true,
            'path' => $thumbRelativePath,
            'url' => '/bninefiles/image/'.$domain.'/'.$id.'?path='.$thumbRelativePath,
        ]);
    }
}
