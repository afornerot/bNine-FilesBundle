<?php

namespace App\Form\Dto;

/**
 * DTO de démonstration pour IconUploadType.
 *
 * Démontre comment passer les options de crop au form type :
 *   - crop_min_size : taille minimale finale (largeur ET hauteur)
 *   - crop_ratio : ratio largeur/hauteur ("1/1", "16/9", "4/3", "free")
 *   - crop_configurable : true si l'user peut modifier min_size et ratio dans la modale
 *   - preview_max_height : hauteur max d'affichage du thumb de retour dans l'objet parent
 */
class DemoIconDto
{
    public ?string $title = null;

    public ?string $avatar = null;

    public ?string $banner = null;

    public ?string $portrait = null;

    public ?string $free = null;

    /** Upload simple SANS crop (le champ est le chemin du fichier original) */
    public ?string $simple = null;
}
