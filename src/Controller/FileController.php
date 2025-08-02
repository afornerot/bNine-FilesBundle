<?php

namespace Bnine\FilesBundle\Controller;

use Bnine\FilesBundle\Security\AbstractFileVoter;
use Bnine\FilesBundle\Service\FileService;
use Oneup\UploaderBundle\Uploader\Response\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    private FileService $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    #[Route('/bninefile/list/{domain}/{id}/{editable}', name: 'bninefiles_files', methods: ['GET'])]
    public function browse(string $domain, int $id, int $editable, Request $request): Response
    {
        $this->denyAccessUnlessGranted($editable ? AbstractFileVoter::EDIT : AbstractFileVoter::VIEW, [$domain, $id]);

        $relativePath = $request->query->get('path', '');

        try {
            $files = $this->fileService->list($domain, (string) $id, $relativePath);

            return $this->render('@BnineFilesBundle/file/browse.html.twig', [
                'domain' => $domain,
                'id' => $id,
                'files' => $files,
                'path' => $relativePath,
                'editable' => $editable,
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

    #[Route('/bninefile/uploadmodal/{domain}/{id}', name: 'bninefiles_files_uploadmodal', methods: ['GET'])]
    public function uploadmodal(string $domain, int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        $relativePath = $request->query->get('path', '');

        return $this->render('@BnineFilesBundle\file\upload.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'endpoint' => 'bninefile',
            'domain' => $domain,
            'id' => $id,
            'path' => $relativePath,
        ]);
    }

    #[Route('/bninefile/uploadfile', name: 'bninefiles_files_uploadfile', methods: ['POST'])]
    public function upload(Request $request): Response|ResponseInterface
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        $domain = $request->query->get('domain');
        $id = $request->query->get('id');
        $relativePath = $request->query->get('path', '');

        $this->denyAccessUnlessGranted(AbstractFileVoter::EDIT, [$domain, $id]);

        if (!$file || !$domain || !$id) {
            return new Response('Invalid parameters', 400);
        }

        $baseDir = $this->getParameter('kernel.project_dir').'/uploads/'.$domain.'/'.$id.'/'.ltrim($relativePath, '/');

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $originalName = $file->getClientOriginalName();
        $file->move($baseDir, $originalName);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/bninefile/delete/{domain}/{id}', name: 'bninefiles_files_delete', methods: ['POST'])]
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

    #[Route('/bninefile/mkdir/{domain}/{id}', name: 'bninefiles_files_mkdir', methods: ['POST'])]
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

    #[Route('/bninefile/download/{domain}/{id}', name: 'bninefiles_files_download', methods: ['GET'])]
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

        return new BinaryFileResponse($absolutePath);
    }
}
