<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonFeaturedItemsTranslation;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\ChildTaxonAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\TaxonAttachedProductAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\FacetConditionType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonFeaturedItemsTranslationType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonMediaType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType as AdminTaxonType;
use Sylius\Bundle\ResourceBundle\Form\Type\FixedCollectionType;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AutoconfigureTag('form.type_extension', ['extended-type' => AdminTaxonType::class])]
final class TaxonTypeExtension extends AbstractTypeExtension
{
    /** @var string[] */
    private array $definedLocalesCodes;

    public function __construct(
        private readonly UploadedImageResizer $uploadedImageResizer,
        #[Autowire(service: 'sylius.translation_locale_provider')]
        TranslationLocaleProviderInterface $translationLocaleProvider,
        private readonly TaxonIconUploader $taxonIconUploader,
    ) {
        $this->definedLocalesCodes = $translationLocaleProvider->getDefinedLocalesCodes();
    }

    private const MEDIA_DISPLAY_MODES = [
        'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_choices.stacked' => 'stacked',
        'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_choices.random' => 'random',
    ];

    private const MEDIA_RIGHT_PRODUCTS_POSITIONS = [
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.start' => Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_START,
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.end' => Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END,
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.random_middle' => Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE,
    ];

    /**
     * Advanced media zones, keyed by their form field suffix.
     *
     * `type` is the value stored in `TaxonImage::$type` and `card_text` tells whether the zone
     * renders its media as product-like cards inside the product listing, which is the only case
     * where the card text toggle makes sense.
     *
     * The optional `display_mode_*` keys declare the display mode field of the zone:
     * `display_mode_choices` holds its options, `display_mode_label` its label and
     * `display_mode_mapped` whether that choice is persisted on the taxon. A zone without them
     * (the universe slider, which always renders as a slider) only exposes its media collection.
     *
     * @var array<string, array{
     *     type: string,
     *     card_text: bool,
     *     display_mode_label?: string,
     *     display_mode_choices?: array<string, string>,
     *     display_mode_mapped?: bool
     * }>
     */
    private const MEDIA_ZONES = [
        'Top' => [
            'type' => 'top',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.top.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_mapped' => true,
            'card_text' => false,
        ],
        'Bottom' => [
            'type' => 'bottom',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.bottom.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_mapped' => true,
            'card_text' => false,
        ],
        'Left' => [
            'type' => 'left',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.left.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_mapped' => true,
            'card_text' => false,
        ],
        'Product' => [
            'type' => 'right',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.right.products_position',
            'display_mode_choices' => self::MEDIA_RIGHT_PRODUCTS_POSITIONS,
            'display_mode_mapped' => true,
            'card_text' => true,
        ],
        'Featured' => [
            'type' => 'featured',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.featured.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_mapped' => true,
            'card_text' => false,
        ],
        'SliderUnivers' => [
            'type' => 'slider_univers',
            'card_text' => false,
        ],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taxon = $builder->getData();
        if (!$taxon instanceof Taxon) {
            $taxon = null;
        }

        $isPersistedTaxon = $taxon?->getId() !== null;
        $hasChildTaxons = $isPersistedTaxon && $taxon->getChildren()->count() > 0;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $form = $event->getForm();
            if ($form->has('images')) {
                $form->remove('images');
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            if ($form->has('images')) {
                $form->remove('images');
            }
        });

        $builder
            ->add('color', ColorType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.color',
            ])
            ->add('icon', TextType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon',
                'attr' => ['data-icon-input' => 'true'],
            ])
            ->add('iconFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon_file',
                'attr' => [
                    'accept' => 'image/*',
                    'data-icon-file' => 'true',
                    'data-max-size' => '80x80',
                ],
            ])
            ->add('iconType', ChoiceType::class, [
                'choices' => [
                    'cyllene_digital_sylius_advanced_taxon.form.icon_type_choices.icon' => 'icon',
                    'cyllene_digital_sylius_advanced_taxon.form.icon_type_choices.image' => 'image',
                ],
                'required' => true,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.icon_type',
                'attr' => ['data-icon-type' => 'true'],
            ])
            ->add('showCustomizationInMenu', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_menu',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_menu_help',
            ])
            ->add('showCustomizationOnTaxonPage', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_on_taxon_page',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_on_taxon_page_help',
            ])
            ->add('showCustomizationInBreadcrumbs', CheckboxType::class, [
                'required' => false,
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_breadcrumbs',
                'help' => 'cyllene_digital_sylius_advanced_taxon.form.show_customization_in_breadcrumbs_help',
            ])
            ->add('isConditionalIndicator', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'data' => $taxon instanceof Taxon ? !$taxon->getFacetConditions()->isEmpty() : false,
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
                    'taxon_left' => $taxon?->getLeft(),
                    'taxon_right' => $taxon?->getRight(),
                    'taxon_root_id' => $taxon?->getRoot()?->getId(),
                ],
            ])
            ->add('featuredItemsTranslations', FixedCollectionType::class, [
                'entries' => $this->definedLocalesCodes,
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
                    'cyllene_digital_sylius_advanced_taxon.form.featured_products_position_choices.before_filters' => 'before_filters',
                    'cyllene_digital_sylius_advanced_taxon.form.featured_products_position_choices.after_filters' => 'after_filters',
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
                'label' => false,
                'required' => false,
                'entry_options' => ['label' => false],
            ])
        ;

        foreach (self::MEDIA_ZONES as $suffix => $zone) {
            // Zones without a display mode (the universe slider) only expose their media collection.
            if (isset($zone['display_mode_label'], $zone['display_mode_choices'], $zone['display_mode_mapped'])) {
                $builder->add('mediaDisplayMode' . $suffix, ChoiceType::class, [
                    'mapped' => $zone['display_mode_mapped'],
                    'choices' => $zone['display_mode_choices'],
                    'label' => $zone['display_mode_label'],
                ]);
            }

            $builder->add('media' . $suffix, CollectionType::class, [
                'mapped' => false,
                'entry_type' => TaxonMediaType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__media_' . $zone['type'] . '__',
                // New media are appended to the zone, so their position is pre-filled with the
                // highest position already used by the zone media, incremented by one.
                'prototype_data' => $this->createPrefilledMedia($taxon, $zone['type']),
                'entry_options' => [
                    'label' => false,
                    'show_card_text' => $zone['card_text'],
                ],
                'required' => false,
                'label' => false,
                'attr' => ['class' => 'media-collection media-collection-' . str_replace('_', '-', $zone['type'])],
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $taxon = $event->getData();
            $form = $event->getForm();

            if (!$taxon instanceof Taxon) {
                return;
            }

            foreach (self::MEDIA_ZONES as $suffix => $zone) {
                $form->get('media' . $suffix)->setData($this->filterMediaByZone($taxon, $zone['type']));
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $taxon = $event->getData();

            if (!$taxon instanceof Taxon) {
                return;
            }

            $iconType = $form->get('iconType')->getData();
            $iconFile = $form->get('iconFile')->getData();

            if (in_array($iconType, ['image', 'pictogram'], true) && $iconFile instanceof UploadedFile) {
                $iconPath = $this->taxonIconUploader->upload($iconFile);

                if ($iconPath !== null) {
                    $taxon->setIcon($iconPath);
                    $taxon->setIconType('image');
                }
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

            // Explicitly sync boolean toggles to avoid edge cases where checkbox
            // mapping is skipped by form composition or partial submits.
            $taxon->setShowCustomizationInMenu((bool) $form->get('showCustomizationInMenu')->getData());
            $taxon->setShowCustomizationOnTaxonPage((bool) $form->get('showCustomizationOnTaxonPage')->getData());
            $taxon->setShowCustomizationInBreadcrumbs((bool) $form->get('showCustomizationInBreadcrumbs')->getData());
            $taxon->setAdvancedFiltersEnabled((bool) $form->get('advancedFiltersEnabled')->getData());
            $taxon->setIsUniverse((bool) $form->get('isUniverse')->getData());
            $taxon->setUniverseBackgroundUseTaxonColor((bool) $form->get('universeBackgroundUseTaxonColor')->getData());
            $taxon->setUniverseTitleBackgroundEnabled((bool) $form->get('universeTitleBackgroundEnabled')->getData());

            // A taxon is treated as conditional when at least one facet condition is configured.
            // Its matching products are materialized by ConditionalTaxonSyncListener, once the
            // taxon has effectively been flushed.
            $taxon->setConditional(!$taxon->getFacetConditions()->isEmpty());

            foreach (self::MEDIA_ZONES as $suffix => $zone) {
                $this->syncZoneMedia($taxon, $form->get('media' . $suffix)->getData(), $zone['type']);
            }
        });
    }

    /**
     * @return Collection<int, TaxonImage>
     */
    private function filterMediaByZone(Taxon $taxon, string $zone): Collection
    {
        $zoneMedia = [];
        foreach ($taxon->getImages() as $image) {
            if ($image instanceof TaxonImage && $image->getType() === $zone) {
                $zoneMedia[] = $image;
            }
        }

        usort($zoneMedia, static fn (TaxonImage $left, TaxonImage $right): int => $left->getPosition() <=> $right->getPosition());

        return new ArrayCollection($zoneMedia);
    }

    private function syncZoneMedia(Taxon $taxon, mixed $submittedMedia, string $zone): void
    {
        if (!is_iterable($submittedMedia)) {
            return;
        }

        $submitted = [];
        foreach ($submittedMedia as $media) {
            if ($media instanceof TaxonImage) {
                $submitted[] = $media;
            }
        }

        $submittedIds = [];
        foreach ($submitted as $media) {
            if ($media->getId() !== null) {
                $submittedIds[] = $media->getId();
            }
        }

        foreach ($taxon->getImages()->toArray() as $existingMedia) {
            if (!$existingMedia instanceof TaxonImage || $existingMedia->getType() !== $zone) {
                continue;
            }

            $existingId = $existingMedia->getId();
            if ($existingId !== null && !in_array($existingId, $submittedIds, true)) {
                $taxon->removeImage($existingMedia);
            }
        }

        foreach ($submitted as $media) {
            $file = $media->getFile();
            if ($file instanceof File) {
                $this->uploadedImageResizer->resizeIfNeeded($file);
            }

            $media->setType($zone);

            if (!$taxon->getImages()->contains($media)) {
                $taxon->addImage($media);
            }
        }
    }

    /**
     * Creates the media added to a zone by the "add" button, with its position pre-filled after the
     * highest position already used by that zone, so a new media is appended to the existing ones.
     */
    private function createPrefilledMedia(?Taxon $taxon, string $zone): TaxonImage
    {
        $highestPosition = null;

        foreach ($taxon?->getImages() ?? [] as $image) {
            if (!$image instanceof TaxonImage || $image->getType() !== $zone) {
                continue;
            }

            $position = $image->getPosition();
            if ($highestPosition === null || $position > $highestPosition) {
                $highestPosition = $position;
            }
        }

        $media = new TaxonImage();
        $media->setPosition($highestPosition === null ? 1 : $highestPosition + 1);

        return $media;
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminTaxonType::class];
    }
}
