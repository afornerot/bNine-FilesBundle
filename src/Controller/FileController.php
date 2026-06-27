<?php

namespace Bnine\FilesBundle\Controller;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Bnine\FilesBundle\Service\FileService;
use Imagine\Gd\Imagine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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

        $baseDir = $this->getParameter('kernel.project_dir').'/uploads/'.$domain.'/'.$id.'/'.ltrim($relativePath, '/');

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $originalName = $file->getClientOriginalName();
        $file->move($baseDir, $originalName);

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
    public function mkdir(string $domain, int $id, Request $request, FileService $fileService): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $path = $request->request->get('path');
        $name = $request->request->get('name');

        if (!$name) {
            return $this->json(['error' => 'Chemin ou nom manquant.'], 400);
        }

        try {
            $fileService->makeDirectory($domain, (string) $id, $path, $name);

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            dump($e->getMessage());

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

        $basePath = $this->fileService->getEntityPath($domain, (string) $id);
        $absolutePath = realpath($basePath.'/'.$filePath);

        // Sécurité : empêche l'accès en dehors du dossier autorisé
        if (!$absolutePath || !str_starts_with($absolutePath, $basePath)) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath);

        // Récupérer le nom de fichier à partir du chemin relatif
        $filename = basename($filePath);

        // Forcer le téléchargement avec le nom de fichier correct
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

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

        $mimeType = MimeTypes::guessMimeTypeForFile($absolutePath);
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

        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $thumbFilename = $filename.'.'.$ext;
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

                $mimeType = MimeTypes::guessMimeTypeForFile($absolutePath);
                if ($mimeType) {
                    $response->headers->set('Content-Type', $mimeType);
                }

                return $response;
            }
        }

        $response = new BinaryFileResponse($thumbPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        $mimeType = MimeTypes::guessMimeTypeForFile($thumbPath);
        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        $response->setMaxAge(86400 * 30);

        return $response;
    }
}
