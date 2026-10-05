<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImageTranslation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag('form.type')]
final class TaxonImageTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.title',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.description',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaxonImageTranslation::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cyllene_digital_advanced_taxon_image_translation';
    }
}
