<?php

namespace Bnine\FilesBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Form type du bundle pour sélectionner un fichier (image unique ou multiple) depuis la gallery.
 *
 * Le type génère un widget caché qui ouvre une modale de sélection via bninefiles.js.
 * - mode 'single'   : le champ stocke un chemin unique (string)
 * - mode 'multiple' : le champ stocke un JSON array de chemins (string JSON)
 *
 * Au lieu d'utiliser un theme Twig fragile, ce form type rend directement
 * le HTML du widget bundle dans `vars['widget_html']` lors du buildView.
 * Le theme Twig du bundle se contente d'afficher cette variable dans le
 * bloc `hidden_widget`. Cela garantit que le widget bundle est toujours rendu
 * correctement, indépendamment de la configuration form_themes de l'app hôte.
 *
 * Le form type construit les data-attributes nécessaires au widget JS :
 *   data-route-gallery-write : URL de la gallery mode write (upload+dossier+suppression)
 *   data-route-gallery-read  : URL de la gallery mode read (sélection seule)
 *   data-route-image         : URL de l'image pour reconstruction d'URL
 *   data-route-thumb         : URL du thumb (preview du fichier sélectionné)
 *   data-mode                : 'single' ou 'multiple'
 *   data-access              : 'write' ou 'read'
 *   data-layout              : 'inline' ou 'column'
 *   data-domain, data-entity-id : identifiants de stockage
 */
class SelectFileType extends AbstractType
{
    private ?UrlGeneratorInterface $router = null;
    private ?Environment $twig = null;

    public function setRouter(?UrlGeneratorInterface $router): void
    {
        $this->router = $router;
    }

    public function setTwig(?Environment $twig): void
    {
        $this->twig = $twig;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mode' => 'single',
            'access' => 'write',
            'layout' => 'inline',
            'domain' => 'sample',
            'entity_id' => 1,
            'required' => false,
            'grid_cols' => 4,
            'show_name' => false,
            'max_height' => null,
            'placeholder' => null,
        ]);

        $resolver->setAllowedValues('mode', ['single', 'multiple']);
        $resolver->setAllowedValues('access', ['write', 'read']);
        $resolver->setAllowedValues('layout', ['inline', 'column']);
        $resolver->setAllowedTypes('domain', 'string');
        $resolver->setAllowedTypes('entity_id', ['int', 'string']);
        $resolver->setAllowedTypes('required', 'bool');
        $resolver->setAllowedTypes('grid_cols', 'int');
        $resolver->setAllowedTypes('show_name', 'bool');
        $resolver->setAllowedTypes('max_height', ['int', 'null']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Calcule les URLs via le router du bundle
        if ($this->router) {
            $view->vars['route_gallery_write'] = $this->router->generate('bninefiles_files_gallery', [
                'domain' => $options['domain'],
                'id' => $options['entity_id'],
                'editable' => 1,
            ]);
            $view->vars['route_gallery_read'] = $this->router->generate('bninefiles_files_gallery', [
                'domain' => $options['domain'],
                'id' => $options['entity_id'],
                'editable' => 0,
            ]);
            $view->vars['route_image'] = $this->router->generate('bninefiles_files_image', [
                'domain' => $options['domain'],
                'id' => $options['entity_id'],
            ]);
            $view->vars['route_thumb'] = $this->router->generate('bninefiles_files_thumbnail', [
                'domain' => $options['domain'],
                'id' => $options['entity_id'],
            ]);
        } else {
            $view->vars['route_gallery_write'] = sprintf('/bninefiles/gallery/%s/%s/1', $options['domain'], $options['entity_id']);
            $view->vars['route_gallery_read'] = sprintf('/bninefiles/gallery/%s/%s/0', $options['domain'], $options['entity_id']);
            $view->vars['route_image'] = sprintf('/bninefiles/image/%s/%s', $options['domain'], $options['entity_id']);
            $view->vars['route_thumb'] = sprintf('/bninefiles/thumbnail/%s/%s', $options['domain'], $options['entity_id']);
        }

        $view->vars['mode'] = $options['mode'];
        $view->vars['access'] = $options['access'];
        $view->vars['layout'] = $options['layout'];
        $view->vars['domain'] = $options['domain'];
        $view->vars['entity_id'] = $options['entity_id'];
        $view->vars['grid_cols'] = $options['grid_cols'];
        $view->vars['show_name'] = $options['show_name'];
        $view->vars['max_height'] = $options['max_height'];
        $view->vars['placeholder'] = $options['placeholder'];

        // Rend directement le HTML du widget bundle via Twig pour contourner
        // le problème de priorité des themes form_div_layout.html.twig.
        // Le theme Twig affichera {{ form.vars.widget_html|raw }} dans hidden_widget.
        if ($this->twig) {
            try {
                $view->vars['widget_html'] = $this->twig->render(
                    '@BnineFilesBundle/file/_select.html.twig',
                    [
                        'mode' => $options['mode'],
                        'access' => $options['access'],
                        'layout' => $options['layout'],
                        'input_name' => $view->vars['full_name'],
                        'input_id' => $view->vars['id'],
                        'domain' => $options['domain'],
                        'entity_id' => $options['entity_id'],
                        'value' => $view->vars['value'],
                        'label' => '',
                        'required' => $options['required'],
                        'show_name' => $options['show_name'],
                        'max_height' => $options['max_height'],
                        'grid_cols' => $options['grid_cols'],
                        'placeholder' => $options['placeholder'],
                    ]
                );
            } catch (\Throwable $e) {
                $view->vars['widget_html'] = '';
            }
        } else {
            $view->vars['widget_html'] = '';
        }
    }

    public function getBlockPrefix(): string
    {
        return 'select_file';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
