<?php

namespace App\Form;

use App\Form\Dto\DemoArticleDto;
use Bnine\FilesBundle\Form\Type\SelectFileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form Type qui utilise SelectFileType du bundle.
 *
 * Le form type du bundle génère un input caché + un widget JS qui ouvre la modale gallery.
 * Pour le mode 'single', le champ stocke un chemin unique (string).
 * Pour le mode 'multiple', le champ stocke un JSON array de chemins (string).
 */
class DemoArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Mon article'],
            ])
            // Image de couverture : SelectFileType en mode single
            ->add('image', SelectFileType::class, [
                'label' => 'Image de couverture (mode single)',
                'mode' => 'single',
                'access' => 'write',
                'layout' => 'inline',
                'domain' => 'sample',
                'entity_id' => 1,
                'required' => true,
                'max_height' => 150,
                'show_name' => true,
            ])
            // Galerie : SelectFileType en mode multiple
            ->add('gallery', SelectFileType::class, [
                'label' => 'Galerie d\'images (mode multiple)',
                'mode' => 'multiple',
                'access' => 'write',
                'layout' => 'inline',
                'domain' => 'sample',
                'entity_id' => 1,
                'required' => false,
                'grid_cols' => 5,
                'show_name' => true,
                'max_height' => 100,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemoArticleDto::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'article';
    }
}
