<?php

namespace Bnine\FilesBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig qui reconstruit l'URL publique d'un fichier stocké.
 *
 * Le bundle stocke les chemins sous forme de string "domain/id/path"
 * (par ex. "sample/1/photo.jpg"). Cette fonction reconstruit l'URL
 * publique correspondante : /bninefiles/image/{domain}/{id}?path={path}.
 *
 * Les chemins commencant par "**public**" ou "uploads" designent des
 * fichiers servis en statique depuis public/. L'application peut tourner sous
 * un sous-repertoire (/ninegate, /monapp...) : le prefixe doit alors etre
 * ajoute, sinon le navigateur demande /medias/... a la racine, qui ne sert pas
 * ce fichier. C'est ce que fait asset() de Symfony ; on s'aligne dessus via
 * le baseUrl de la requete courante.
 */
class BnineFileExtension extends AbstractExtension
{
    public function __construct(
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('bninefile', [$this, 'bninefile'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Prefixe un chemin statique par le sous-repertoire de l'application.
     */
    private function prefix(string $path): string
    {
        $baseUrl = $this->requestStack?->getCurrentRequest()?->getBaseUrl() ?? '';

        return $baseUrl.$path;
    }

    public function bninefile(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Routes du bundle : deja absolues, le prefixe est applique par le router.
        if (str_starts_with($value, '/bninefiles/')) {
            return $value;
        }

        if (str_starts_with($value, 'bundles/')) {
            return $this->prefix('/'.$value);
        }

        if (str_starts_with($value, 'uploads/')) {
            return $this->prefix('/'.$value);
        }

        if (str_starts_with($value, '**public**/')) {
            return $this->prefix('/'.str_replace('**public**/', '', $value));
        }

        if (str_starts_with($value, '/')) {
            return $this->prefix($value);
        }

        // Format normalisé "domain/id/path" : l'id est toujours numérique
        // (toutes les routes du bundle déclarent {id} en int). Exiger un id
        // numérique évite de prendre un chemin statique de l'application
        // (ex. "medias/avatar/admin.jpg") pour un chemin du bundle, ce qui
        // produisait /bninefiles/image/medias/avatar/... et un 404.
        $parts = explode('/', $value, 3);
        if (count($parts) === 3 && '' !== $parts[0] && '' !== $parts[1] && ctype_digit($parts[1])) {
            [$domain, $id, $path] = $parts;

            return $this->prefix('/bninefiles/image/'.$domain.'/'.$id.'?path='.rawurlencode($path));
        }

        // Format non reconnu (ex: ancien chemin '_thumbs/xxx.jpg', chemin incomplet, etc.) :
        // on retourne la valeur telle quelle (préfixée d'un '/' si nécessaire) au lieu
        // de lever une exception, pour ne pas casser le rendu des templates.
        return $this->prefix(str_starts_with($value, '/') ? $value : '/'.$value);
    }
}

