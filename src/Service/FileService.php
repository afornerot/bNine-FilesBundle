<?php

namespace Bnine\FilesBundle\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;

class FileService
{
    private string $basePath;
    private ?FilesystemOperator $storage;
    private bool $s3;

    public function __construct(KernelInterface $kernel, ?FilesystemOperator $storage = null, bool $s3 = false)
    {
        $this->storage = $storage;
        $this->s3 = $s3;
        $projectDir = $kernel->getProjectDir();
        $this->basePath = $projectDir.'/uploads';

        if (!$this->isS3() && !is_dir($this->basePath)) {
            $fs = new Filesystem();
            try {
                $fs->mkdir($this->basePath, 0775);
            } catch (IOExceptionInterface $e) {
                throw new \RuntimeException('Impossible de créer le dossier /uploads : '.$e->getMessage());
            }
        }
    }

    public function init(string $domain, string $id): void
    {
        $path = $domain.'/'.$id;

        if ($this->isS3()) {
            if (!$this->storage->directoryExists($path)) {
                $this->storage->createDirectory($path);
            }

            return;
        }

        $entityPath = $this->getEntityPath($domain, $id);
        if (!is_dir($entityPath)) {
            $fs = new Filesystem();
            try {
                $fs->mkdir($entityPath, 0775);
            } catch (IOExceptionInterface $e) {
                throw new \RuntimeException(sprintf('Impossible de créer le répertoire pour %s/%s : %s', $domain, $id, $e->getMessage()));
            }
        }
    }

    public function list(string $domain, string $id, string $relativePath = ''): array
    {
        if ($this->isS3()) {
            return $this->listViaStorage($domain, $id, $relativePath);
        }

        return $this->listViaFilesystem($domain, $id, $relativePath);
    }

    public function delete(string $domain, string $id, string $relativePath): void
    {
        if ($this->isS3()) {
            $path = $domain.'/'.$id.'/'.ltrim($relativePath, '/');

            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
            } elseif ($this->storage->directoryExists($path)) {
                $this->storage->deleteDirectory($path);
            }

            return;
        }

        $baseEntityPath = $this->getEntityPath($domain, $id);
        $targetPath = realpath($baseEntityPath.'/'.ltrim($relativePath, '/'));

        if (!$targetPath || !str_starts_with($targetPath, $baseEntityPath)) {
            throw new NotFoundHttpException('Fichier ou dossier non autorisé.');
        }

        $fs = new Filesystem();
        try {
            $fs->remove($targetPath);
        } catch (IOExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de la suppression : '.$e->getMessage());
        }
    }

    public function makeDirectory(string $domain, string $id, string $relativePath, string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9-_]+$/', $name)) {
            throw new \InvalidArgumentException('Nom de dossier invalide.');
        }

        if ($this->isS3()) {
            $path = $domain.'/'.$id.'/'.ltrim($relativePath, '/'.$name);

            $checkPath = rtrim($path, '/').'/';
            $entries = iterator_to_array($this->storage->listContents($checkPath));
            foreach ($entries as $entry) {
                $basename = basename($entry->path());
                if ($basename === $name) {
                    throw new \RuntimeException('Le dossier existe déjà.');
                }
            }

            $newPath = rtrim($domain.'/'.$id.'/'.ltrim($relativePath, '/'), '/').'/'.$name;
            $this->storage->createDirectory($newPath);

            return;
        }

        $baseEntityPath = $this->getEntityPath($domain, $id);
        $targetPath = realpath($baseEntityPath.'/'.ltrim($relativePath, '/'));
        $newDir = $targetPath.'/'.$name;

        if (file_exists($newDir)) {
            throw new \RuntimeException('Le dossier existe déjà.');
        }

        if (!mkdir($newDir, 0775, true)) {
            throw new \RuntimeException('Impossible de créer le dossier.');
        }
    }

    public function getEntityPath(string $domain, string $id): string
    {
        return $this->basePath.'/'.$domain.'/'.$id;
    }

    public function getRelativePath(string $domain, string $id, string $filePath): string
    {
        return $domain.'/'.$id.'/'.ltrim($filePath, '/');
    }

    public function hasStorage(): bool
    {
        return $this->storage !== null;
    }

    public function isS3(): bool
    {
        return $this->s3 && $this->storage !== null;
    }

    public function getStorage(): ?FilesystemOperator
    {
        return $this->storage;
    }

    private function listViaStorage(string $domain, string $id, string $relativePath): array
    {
        $basePath = $domain.'/'.$id.'/'.ltrim($relativePath, '/');
        $basePath = rtrim($basePath, '/').'/';

        $results = [];
        $entries = $this->storage->listContents($basePath);

        foreach ($entries as $entry) {
            $fullPath = $entry->path();
            $basename = basename($fullPath);
            if (str_starts_with($basename, '_')) {
                continue;
            }

            $isDir = $entry->isDir();
            $relativeToEntity = ltrim(str_replace($domain.'/'.$id.'/', '', $fullPath), '/');

            $results[] = [
                'name' => $basename,
                'isDirectory' => $isDir,
                'path' => $relativeToEntity,
            ];
        }

        usort($results, function ($a, $b) {
            if ($a['isDirectory'] === $b['isDirectory']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $a['isDirectory'] ? -1 : 1;
        });

        return $results;
    }

    private function listViaFilesystem(string $domain, string $id, string $relativePath): array
    {
        $targetPath = $this->getEntityPath($domain, $id).'/'.ltrim($relativePath, '/');
        $realPath = realpath($targetPath);

        $baseEntityPath = $this->getEntityPath($domain, $id);
        if (!$realPath || !str_starts_with($realPath, $baseEntityPath)) {
            throw new NotFoundHttpException('Répertoire non autorisé ou inexistant.');
        }

        $finder = new Finder();
        $finder->depth('== 0')->in($realPath);

        $results = [];
        foreach ($finder as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_')) {
                continue;
            }
            $results[] = [
                'name' => $file->getFilename(),
                'isDirectory' => $file->isDir(),
                'path' => ltrim(str_replace($baseEntityPath, '', $file->getRealPath()), '/'),
            ];
        }

        usort($results, function ($a, $b) {
            if ($a['isDirectory'] === $b['isDirectory']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $a['isDirectory'] ? -1 : 1;
        });

        return $results;
    }
}
