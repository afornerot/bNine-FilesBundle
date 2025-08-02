<?php

namespace Bnine\FilesBundle\Service;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;

class FileService
{
    private string $basePath;
    private Filesystem $filesystem;

    public function __construct(KernelInterface $kernel)
    {
        $this->filesystem = new Filesystem();
        $projectDir = $kernel->getProjectDir(); // chemin racine du projet
        $this->basePath = $projectDir.'/uploads';

        if (!is_dir($this->basePath)) {
            // On crée le dossier uploads s'il n'existe pas
            try {
                $this->filesystem->mkdir($this->basePath, 0775);
            } catch (IOExceptionInterface $e) {
                throw new \RuntimeException('Impossible de créer le dossier /uploads : '.$e->getMessage());
            }
        }
    }

    /**
     * Initialise un répertoire pour une entité (ex: project/123)
     */
    public function init(string $domain, string $id): void
    {
        $entityPath = $this->getEntityPath($domain, $id);
        if (!is_dir($entityPath)) {
            try {
                $this->filesystem->mkdir($entityPath, 0775);
            } catch (IOExceptionInterface $e) {
                throw new \RuntimeException(sprintf('Impossible de créer le répertoire pour %s/%s : %s', $domain, $id, $e->getMessage()));
            }
        }
    }

    /**
     * Liste les fichiers d’un répertoire lié à une entité (ex: project/123)
     */
    public function list(string $domain, string $id, string $relativePath = ''): array
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
            $results[] = [
                'name' => $file->getFilename(),
                'isDirectory' => $file->isDir(),
                'path' => ltrim(str_replace($baseEntityPath, '', $file->getRealPath()), '/'),
            ];
        }

        // Trie : dossiers d'abord (alpha), puis fichiers (alpha)
        usort($results, function ($a, $b) {
            if ($a['isDirectory'] === $b['isDirectory']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $a['isDirectory'] ? -1 : 1;
        });

        return $results;
    }

    /**
     * Supprime un fichier ou dossier (de façon sécurisée)
     */
    public function delete(string $domain, string $id, string $relativePath): void
    {
        $baseEntityPath = $this->getEntityPath($domain, $id);
        $targetPath = realpath($baseEntityPath.'/'.ltrim($relativePath, '/'));

        if (!$targetPath || !str_starts_with($targetPath, $baseEntityPath)) {
            throw new NotFoundHttpException('Fichier ou dossier non autorisé.');
        }

        try {
            $this->filesystem->remove($targetPath);
        } catch (IOExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de la suppression : '.$e->getMessage());
        }
    }

    /**
     * Supprime un fichier ou dossier (de façon sécurisée)
     */
    public function makeDirectory(string $domain, string $id, string $relativePath, string $name): void
    {
        $baseEntityPath = $this->getEntityPath($domain, $id);
        $targetPath = realpath($baseEntityPath.'/'.ltrim($relativePath, '/'));
        $newDir = $targetPath.'/'.$name;

        if (!preg_match('/^[a-zA-Z0-9-_]+$/', $name)) {
            throw new \InvalidArgumentException('Nom de dossier invalide.');
        }

        if (file_exists($newDir)) {
            throw new \RuntimeException('Le dossier existe déjà.');
        }

        if (!mkdir($newDir, 0775, true)) {
            throw new \RuntimeException('Impossible de créer le dossier.');
        }
    }

    /**
     * Construit le chemin absolu d’un domaine/id
     */
    public function getEntityPath(string $domain, string $id): string
    {
        return $this->basePath.'/'.$domain.'/'.$id;
    }
}
