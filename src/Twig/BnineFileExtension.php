<?php

namespace Bnine\FilesBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig qui reconstruit l'URL publique d'un fichier stocké.
 *
 * Le bundle stocke les chemins sous forme de string "domain/id/path"
 * (par ex. "sample/1/photo.jpg"). Cette fonction reconstruit l'URL
 * publique correspondante : /bninefiles/image/{domain}/{id}?path={path}.
 */
class BnineFileExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('bninefile', [$this, 'bninefile'], ['is_safe' => ['html']]),
        ];
    }

    public function bninefile(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/bninefiles/')) {
            return $value;
        }

        if (str_starts_with($value, 'bundles/')) {
            return '/'.$value;
        }

        if (str_starts_with($value, 'uploads/')) {
            return '/'.$value;
        }

        if (str_starts_with($value, '**public**/')) {
            return '/'.str_replace('**public**/', '', $value);
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        // Format normalisé "domain/id/path" : on extrait domain, id et path.
        $parts = explode('/', $value, 3);
        if (count($parts) === 3 && '' !== $parts[0] && '' !== $parts[1]) {
            [$domain, $id, $path] = $parts;

            return '/bninefiles/image/'.$domain.'/'.$id.'?path='.rawurlencode($path);
        }

        throw new \RuntimeException(sprintf(
            'La valeur passée à bninefile() doit être au format "domain/id/path" (reçu : %s). '
            .'Assurez-vous que le widget BnineFileExtension est utilisé après passage par '
            .'un SelectFileType, IconUploadType ou via le controller d\'upload/crop du bundle.',
            $value
        ));
    }
}

