<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonFeaturedItemsTranslation;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\ChildTaxonAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\TaxonAttachedProductAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\FacetConditionType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonFeaturedItemsTranslationType;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType as AdminTaxonType;
use Sylius\Bundle\ResourceBundle\Form\Type\FixedCollectionType;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * Listing, featured items, universe and conditions of a taxon.
 */
final class TaxonTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TranslationLocaleProviderInterface $translationLocaleProvider,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taxon = $builder->getData();
        if (!$taxon instanceof AdvancedTaxonInterface) {
            $taxon = null;
        }

        $isPersistedTaxon = $taxon?->getId() !== null;
        $hasChildTaxons = $isPersistedTaxon && $taxon->getChildren()->count() > 0;

        $builder
            ->add('isConditionalIndicator', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $taxon !== null && !$taxon->getFacetConditions()->isEmpty(),
                'disabled' => true,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.conditional',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.conditional_auto_help',
                'attr' => [
                    'data-facet-conditional-indicator' => 'true',
                ],
            ])
            ->add('advancedFiltersEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.advanced_filters_enabled',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.advanced_filters_enabled_help',
            ])
            ->add('isUniverse', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.is_universe',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.is_universe_help',
            ])
            ->add('universeBackgroundUseTaxonColor', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.universe_background_use_taxon_color',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.universe_background_use_taxon_color_help',
            ])
            ->add('universeTitleBackgroundEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.universe_title_background_enabled',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.universe_title_background_enabled_help',
            ])
            ->add('includeChildrenProducts', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.include_children_products',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.include_children_products_description',
            ])
            ->add('featuredChildren', ChildTaxonAutocompleteType::class, [
                'multiple' => true,
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_children',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_children_description',
                'disabled' => !$hasChildTaxons,
                'extra_options' => [
                    'parent_taxon_id' => $taxon?->getId(),
                ],
            ])
            ->add('featuredProducts', TaxonAttachedProductAutocompleteType::class, [
                'multiple' => true,
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_products',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_products_description',
                'disabled' => !$isPersistedTaxon,
                'extra_options' => [
                    'taxon_id' => $taxon?->getId(),
                ],
            ])
            ->add('featuredItemsTranslations', FixedCollectionType::class, [
                'entries' => $this->translationLocaleProvider->getDefinedLocalesCodes(),
                'entry_type' => TaxonFeaturedItemsTranslationType::class,
                'entry_name' => static fn (string $localeCode): string => $localeCode,
                'entry_options' => static fn (string $localeCode): array => [
                    'required' => false,
                    'label' => false,
                ],
                'by_reference' => false,
                'label' => false,
                'required' => false,
            ])
            ->add('isFeaturedProductsActive', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.is_featured_products_active',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.is_featured_products_active_description',
            ])
            ->add('featuredProductsPosition', ChoiceType::class, [
                'choices' => [
                    'cyllene_digital_sylius_advanced_taxon.form.featured_products_position_choices.before_filters' => AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS,
                    'cyllene_digital_sylius_advanced_taxon.form.featured_products_position_choices.after_filters' => AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS,
                ],
                'required' => true,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.featured_products_position',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.featured_products_position_description',
            ])
            ->add('isSlider', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.is_slider',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.is_slider_description',
            ])
            ->add('facetConditions', CollectionType::class, [
                'entry_type' => FacetConditionType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__facet__',
                'constraints' => [new Valid()],
                'label' => false,
                'required' => false,
                'entry_options' => ['label' => false],
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $taxon = $event->getData();

            if (!$taxon instanceof AdvancedTaxonInterface) {
                return;
            }

            $featuredItemsTranslations = $form->get('featuredItemsTranslations')->getData();
            if (is_iterable($featuredItemsTranslations)) {
                foreach ($featuredItemsTranslations as $localeCode => $translation) {
                    if (!$translation instanceof TaxonFeaturedItemsTranslation || !is_string($localeCode)) {
                        continue;
                    }

                    $translation->setLocale($localeCode);
                    $taxon->addFeaturedItemsTranslation($translation);
                }
            }

            // A taxon is treated as conditional when at least one facet condition is configured.
            // Its matching products are materialized by ConditionalTaxonSyncListener, once the
            // taxon has effectively been flushed.
            $taxon->setConditional(!$taxon->getFacetConditions()->isEmpty());
        });
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [AdminTaxonType::class];
    }
}
