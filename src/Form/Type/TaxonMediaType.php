<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

final class TaxonMediaType extends AbstractType
{
    /**
     * @param class-string<AdvancedTaxonImageInterface> $taxonImageClass
     */
    public function __construct(
        private readonly string $taxonImageClass,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['show_url']) {
            $builder->add('url', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, groups: ['Default', 'sylius'])],
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.url',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.media.url_help',
            ]);
        }

        $builder
            ->add('file', FileType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.path',
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'empty_data' => '0',
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.media.position',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.media.position_help',
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
            'data_class' => $this->taxonImageClass,
            'show_card_text' => false,
            'show_url' => true,
        ]);

        $resolver->setAllowedTypes('show_card_text', ['bool']);
        $resolver->setAllowedTypes('show_url', ['bool']);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'cyllene_digital_advanced_taxon_media';
    }
}
