<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag('form.type')]
final class TaxonMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', TextType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.url',
            ])
            ->add('file', FileType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.path',
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.position',
            ])
        ;

        // The card text toggle only drives the product-like cards rendered inside the taxon
        // product listing, so every other zone must not expose it.
        if ($options['show_card_text']) {
            $builder->add('showCardText', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.show_card_text',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.media.show_card_text_help',
            ]);
        }

        $builder->add('translations', ResourceTranslationsType::class, [
            'entry_type' => TaxonImageTranslationType::class,
            'label' => 'sylius.ui.translations',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaxonImage::class,
            'show_card_text' => false,
        ]);

        $resolver->setAllowedTypes('show_card_text', ['bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'cyllene_digital_advanced_taxon_media';
    }
}
