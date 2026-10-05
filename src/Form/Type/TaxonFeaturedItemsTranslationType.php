<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonFeaturedItemsTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

final class TaxonFeaturedItemsTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('featuredProductsTitle', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, groups: ['Default', 'sylius'])],
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_products_title',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_products_title_help',
            ])
            ->add('featuredProductsDescription', TextareaType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_products_description',
            ])
            ->add('featuredChildrenTitle', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, groups: ['Default', 'sylius'])],
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_children_title',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_children_title_help',
            ])
            ->add('featuredChildrenDescription', TextareaType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.featured_children_description',
            ])
            ->add('universePageTitle', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, groups: ['Default', 'sylius'])],
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_page_title',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_page_title_help',
            ])
            ->add('universePageDescription', TextareaType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_page_description',
            ])
            ->add('universeFeaturedProductsTitle', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255, groups: ['Default', 'sylius'])],
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_featured_products_title',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_featured_products_title_help',
            ])
            ->add('universeFeaturedProductsDescription', TextareaType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_items.universe_featured_products_description',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaxonFeaturedItemsTranslation::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'cyllene_digital_advanced_taxon_featured_items_translation';
    }
}
