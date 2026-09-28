<?php

namespace App\Form;

use App\Form\Dto\DemoIconDto;
use Bnine\FilesBundle\Form\Type\IconUploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemoIconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            // Avatar carré 300x300, configurable=false, preview 100px (défaut)
            ->add('avatar', IconUploadType::class, [
                'label' => 'Avatar carré (configurable=false, preview 100px)',
                'icon_label' => 'Avatar carré',
                'icon_empty_preview' => '',
                'icon_domain' => 'avatar',
                'icon_entity_id' => 0,
                'crop' => true,
                'crop_min_size' => 300,
                'crop_ratio' => '1/1',
                'crop_configurable' => false,
                'preview_max_height' => 100,
            ])
            // Avatar bannière 16/9, configurable=true, preview 250px
            ->add('banner', IconUploadType::class, [
                'label' => 'Avatar bannière (configurable=true, preview 250px)',
                'icon_label' => 'Bannière',
                'icon_empty_preview' => '',
                'icon_domain' => 'avatar',
                'icon_entity_id' => 0,
                'crop' => true,
                'crop_min_size' => 400,
                'crop_ratio' => '16/9',
                'crop_configurable' => true,
                'preview_max_height' => 250,
            ])
            // Avatar portrait 3/4, configurable=true, preview 200px
            ->add('portrait', IconUploadType::class, [
                'label' => 'Avatar portrait (configurable=true, preview 200px)',
                'icon_label' => 'Portrait',
                'icon_empty_preview' => '',
                'icon_domain' => 'avatar',
                'icon_entity_id' => 0,
                'crop' => true,
                'crop_min_size' => 250,
                'crop_ratio' => '3/4',
                'crop_configurable' => true,
                'preview_max_height' => 200,
            ])
            // Avatar libre, configurable=true, ratio "free", preview 300px
            ->add('free', IconUploadType::class, [
                'label' => 'Avatar libre (ratio=free, configurable=true, preview 300px)',
                'icon_label' => 'Libre',
                'icon_empty_preview' => '',
                'icon_domain' => 'avatar',
                'icon_entity_id' => 0,
                'crop' => true,
                'crop_min_size' => 200,
                'crop_ratio' => 'free',
                'crop_configurable' => true,
                'preview_max_height' => 300,
            ])
            // Avatar SANS crop : simple upload d'image, pas de modale crop.
            // On garde le domaine 'avatar' (public) : le controller crée automatiquement
            // un thumb et renvoie le path du thumb au widget parent, donc le path
            // stocké dans l'input est du type "_thumbs/{file}.jpg".
            ->add('simple', IconUploadType::class, [
                'label' => 'Avatar SANS crop (simple upload)',
                'icon_label' => 'Avatar simple',
                'icon_empty_preview' => '',
                'icon_domain' => 'avatar',
                'icon_entity_id' => 0,
                'crop' => false,
                'preview_max_height' => 150,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemoIconDto::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'demo_icon';
    }
}
