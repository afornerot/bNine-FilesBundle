<?php

namespace Bnine\FilesBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class IconUploadType extends AbstractType
{
    private ?UrlGeneratorInterface $router = null;

    public function setRouter(?UrlGeneratorInterface $router): void
    {
        $this->router = $router;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'form-control icon-input'],
            'icon_endpoint' => 'icon',
            'icon_label' => 'Icon',
            'icon_empty_preview' => 'medias/icon/icon_pin.png',
            // Identifiants de stockage (utilisés pour construire l'URL d'upload via le router)
            'icon_domain' => 'avatar',
            'icon_entity_id' => 0,
            // Options de crop (toutes optionnelles, transmises en query string à la modale d'upload)
            'crop' => false,
            'crop_min_size' => 300,
            'crop_ratio' => '1/1',
            'crop_configurable' => false,
            // Taille d'affichage du thumb de retour (px, défaut 100)
            'preview_max_height' => 100,
            // Classes CSS appliquees a la balise <img> de preview.
            // 'img_class' : classes ajoutees en plus des classes par defaut
            //               (bigavatar, icon-upload-preview). Mettez par ex.
            //               'rounded-circle shadow-sm' pour un avatar rond.
            // 'img_class_replace' : si true, remplace les classes par defaut
            //                       au lieu de les ajouter.
            'img_class' => '',
            'img_class_replace' => false,
        ]);
        $resolver->setAllowedTypes('icon_domain', 'string');
        $resolver->setAllowedTypes('icon_entity_id', ['int', 'string']);
        $resolver->setAllowedTypes('img_class', 'string');
        $resolver->setAllowedTypes('img_class_replace', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Construit l'URL d'upload via le router du bundle (utilise la route bninefiles_files_uploadmodal)
        if ($this->router) {
            $uploadUrl = $this->router->generate('bninefiles_files_uploadmodal', [
                'domain' => $options['icon_domain'],
                'id' => $options['icon_entity_id'],
            ]);
        } else {
            // Fallback si le router n'est pas injecté (rare)
            $uploadUrl = sprintf(
                '/bninefiles/uploadmodal/%s/%s',
                $options['icon_domain'],
                $options['icon_entity_id']
            );
        }

        // Ajoute les query params : crop d'abord, puis options de crop
        $query = [];
        if ($options['crop']) {
            $query['crop'] = 1;
        }
        if ($options['crop_min_size'] !== 300) {
            $query['min_size'] = (int) $options['crop_min_size'];
        }
        if ($options['crop_ratio'] !== '1/1') {
            $query['ratio'] = $options['crop_ratio'];
        }
        if ($options['crop_configurable']) {
            $query['configurable'] = 1;
        }

        if (!empty($query)) {
            $uploadUrl .= '?' . http_build_query($query);
        }

        $view->vars['attr']['data-upload-url'] = $uploadUrl;
        $view->vars['attr']['data-icon-endpoint'] = $options['icon_endpoint'];
        $view->vars['attr']['data-icon-label'] = $options['icon_label'];
        $view->vars['attr']['data-icon-empty-preview'] = $options['icon_empty_preview'];
        $view->vars['attr']['data-preview-max-height'] = (int) $options['preview_max_height'];
        $view->vars['icon_endpoint'] = $options['icon_endpoint'];
        $view->vars['icon_label'] = $options['icon_label'];
        $view->vars['preview_max_height'] = (int) $options['preview_max_height'];
        $view->vars['img_class'] = $options['img_class'];
        $view->vars['img_class_replace'] = (bool) $options['img_class_replace'];
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    /**
     * Permet au theme Twig du bundle (_theme.html.twig) d'intercepter
     * le rendu du widget IconUploadType via le prefixe 'icon_upload'.
     *
     * Sans ce prefixe, block_prefixes ne contient que ['hidden', 'form']
     * (car getParent() = HiddenType) et la branche icon_upload du theme
     * ne match jamais.
     */
    public function getBlockPrefix(): string
    {
        return 'icon_upload';
    }
}
