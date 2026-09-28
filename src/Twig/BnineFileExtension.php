<?php

namespace Bnine\FilesBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig qui reconstruit l'URL publique d'un fichier stocké.
 *
 * Le bundle stocke les chemins sous forme de string ("domain/id/path").
 * Cette fonction reconstruit l'URL publique correspondante.
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

        if (str_starts_with($value, '/')) {
            return $value;
        }

        if (str_starts_with($value, 'uploads/')) {
            return '/'.$value;
        }

        $parts = explode('/', $value, 3);
        if (count($parts) === 3) {
            [$domain, $id, $path] = $parts;

            return '/bninefiles/image/'.$domain.'/'.$id.'?path='.rawurlencode($path);
        }

        return '/'.$value;
    }
}

