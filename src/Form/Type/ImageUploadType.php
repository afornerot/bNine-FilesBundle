<?php

namespace Bnine\FilesBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // No extra fields needed - just stores the path as a string
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'form-control image-upload-input'],
            'domain' => 'uploads',
            'entityId' => '0',
            'maxWidth' => 300,
            'imageOnly' => true,
            'label' => 'Image',
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-domain'] = $options['domain'];
        $view->vars['attr']['data-entity-id'] = $options['entityId'];
        $view->vars['attr']['data-max-width'] = $options['maxWidth'];
        $view->vars['attr']['data-image-only'] = $options['imageOnly'] ? '1' : '0';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
