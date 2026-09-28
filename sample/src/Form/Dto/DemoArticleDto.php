<?php

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de démonstration pour le widget bNine-FilesBundle.
 *
 * Le champ `image` stocke un chemin unique (string).
 * Le champ `gallery` stocke une liste de chemins sérialisée en JSON (string).
 * Le widget bundle fait la conversion array ↔ string automatiquement.
 */
class DemoArticleDto
{
    public ?string $title = null;

    #[Assert\NotBlank(message: 'L\'image est obligatoire.')]
    public ?string $image = null;

    /**
     * JSON array de chemins.
     * Ex: '["avatar/0/abc.jpg","avatar/0/def.jpg"]'
     * Vide par défaut : '[]'
     */
    public string $gallery = '[]';
}
