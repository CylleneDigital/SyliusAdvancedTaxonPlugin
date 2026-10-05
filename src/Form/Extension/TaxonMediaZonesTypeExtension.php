<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonMediaType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType as AdminTaxonType;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * The media zones of a taxon, in place of the Sylius images collection. Every media is a Sylius
 * taxon image, its zone stored in the image type.
 */
final class TaxonMediaZonesTypeExtension extends AbstractTypeExtension
{
    private const array MEDIA_DISPLAY_MODES = [
        'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_choices.stacked' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED,
        'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_choices.random' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_RANDOM,
    ];

    /**
     * The full-width zones can also show their media as a slider.
     */
    private const array MEDIA_DISPLAY_MODES_WITH_SLIDER = [
        ...self::MEDIA_DISPLAY_MODES,
        'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_choices.slider' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_SLIDER,
    ];

    private const array MEDIA_RIGHT_PRODUCTS_POSITIONS = [
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.start' => AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_START,
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.end' => AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_END,
        'cyllene_digital_sylius_advanced_taxon.form.media.right_products_position_choices.random_middle' => AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE,
    ];

    /**
     * Advanced media zones, keyed by their form field suffix.
     *
     * `type` is the value stored in the taxon image `type` and `card_text` tells whether the zone
     * renders its media as product-like cards inside the product listing, which is the only case
     * where the card text toggle makes sense. `url` tells whether the zone renders its media as
     * links: the other zones ignore a destination URL.
     *
     * The optional `display_mode_*` keys declare the display mode field of the zone:
     * `display_mode_choices` holds its options and `display_mode_label` its label. A zone without
     * them (the main image, the universe slider) only exposes its media collection.
     *
     * @var array<string, array{
     *     type: string,
     *     card_text: bool,
     *     url: bool,
     *     display_mode_label?: string,
     *     display_mode_choices?: array<string, string>,
     *     display_mode_help?: string
     * }>
     */
    private const array MEDIA_ZONES = [
        'Main' => [
            'type' => self::MAIN_IMAGE_ZONE,
            'card_text' => false,
            'url' => false,
        ],
        'Top' => [
            'type' => 'top',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.top.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES_WITH_SLIDER,
            'display_mode_help' => 'cyllene_digital_sylius_advanced_taxon.form.media.slider_display_mode_help',
            'card_text' => false,
            'url' => false,
        ],
        'Bottom' => [
            'type' => 'bottom',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.bottom.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES_WITH_SLIDER,
            'display_mode_help' => 'cyllene_digital_sylius_advanced_taxon.form.media.slider_display_mode_help',
            'card_text' => false,
            'url' => false,
        ],
        'Left' => [
            'type' => 'left',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.left.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_help' => 'cyllene_digital_sylius_advanced_taxon.form.media.display_mode_help',
            'card_text' => false,
            'url' => false,
        ],
        'Product' => [
            'type' => 'right',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.right.products_position',
            'display_mode_choices' => self::MEDIA_RIGHT_PRODUCTS_POSITIONS,
            'card_text' => true,
            'url' => true,
        ],
        'Featured' => [
            'type' => 'featured',
            'display_mode_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.zones.featured.display_mode',
            'display_mode_choices' => self::MEDIA_DISPLAY_MODES,
            'display_mode_help' => 'cyllene_digital_sylius_advanced_taxon.form.media.spotlight_display_mode_help',
            'card_text' => false,
            'url' => false,
        ],
        'SliderUniverse' => [
            'type' => 'slider_universe',
            'card_text' => false,
            'url' => true,
        ],
    ];

    /**
     * Zone of the main taxon image, the one Sylius shows on the taxon page. An image stored by Sylius
     * without type belongs to it, and gets the type once the taxon is saved.
     */
    private const string MAIN_IMAGE_ZONE = 'main';

    /**
     * @param FactoryInterface<AdvancedTaxonImageInterface> $taxonImageFactory
     */
    public function __construct(
        private readonly UploadedImageResizer $uploadedImageResizer,
        private readonly FactoryInterface $taxonImageFactory,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $taxon = $builder->getData();
        if (!$taxon instanceof AdvancedTaxonInterface) {
            $taxon = null;
        }

        // Added by Sylius in its own buildForm, which runs before this extension.
        $builder->remove('images');

        foreach (self::MEDIA_ZONES as $suffix => $zone) {
            if (isset($zone['display_mode_label'], $zone['display_mode_choices'])) {
                $builder->add('mediaDisplayMode' . $suffix, ChoiceType::class, [
                    'choices' => $zone['display_mode_choices'],
                    'label' => $zone['display_mode_label'],
                    'help' => $zone['display_mode_help'] ?? null,
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
                'button_add_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.add',
                'button_delete_label' => 'cyllene_digital_sylius_advanced_taxon.form.media.remove',
                // New media are appended to the zone, so their position is pre-filled with the
                // highest position already used by the zone media, incremented by one.
                'prototype_data' => $this->createPrefilledMedia($taxon, $zone['type']),
                'entry_options' => [
                    'label' => false,
                    'show_card_text' => $zone['card_text'],
                    'show_url' => $zone['url'],
                ],
                'required' => false,
                'label' => false,
                // The media are attached to the taxon after the validation listener ran: without
                // this, a new media would skip the Sylius file constraints (type, size).
                'constraints' => [new Valid()],
                'attr' => ['class' => 'media-collection media-collection-' . str_replace('_', '-', $zone['type'])],
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $taxon = $event->getData();
            if (!$taxon instanceof AdvancedTaxonInterface) {
                return;
            }

            foreach (self::MEDIA_ZONES as $suffix => $zone) {
                $event->getForm()->get('media' . $suffix)->setData($this->filterMediaByZone($taxon, $zone['type']));
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $taxon = $event->getData();
            if (!$taxon instanceof AdvancedTaxonInterface) {
                return;
            }

            foreach (self::MEDIA_ZONES as $suffix => $zone) {
                $this->syncZoneMedia($taxon, $event->getForm()->get('media' . $suffix)->getData(), $zone['type']);
            }
        });
    }

    /**
     * @return Collection<int, AdvancedTaxonImageInterface>
     */
    private function filterMediaByZone(AdvancedTaxonInterface $taxon, string $zone): Collection
    {
        $zoneMedia = [];
        foreach ($taxon->getImages() as $image) {
            if ($image instanceof AdvancedTaxonImageInterface && self::zoneOf($image) === $zone) {
                $zoneMedia[] = $image;
            }
        }

        usort($zoneMedia, static fn (AdvancedTaxonImageInterface $left, AdvancedTaxonImageInterface $right): int => $left->getPosition() <=> $right->getPosition());

        return new ArrayCollection($zoneMedia);
    }

    private function syncZoneMedia(AdvancedTaxonInterface $taxon, mixed $submittedMedia, string $zone): void
    {
        if (!is_iterable($submittedMedia)) {
            return;
        }

        $submitted = [];
        foreach ($submittedMedia as $media) {
            if ($media instanceof AdvancedTaxonImageInterface) {
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
            if (!$existingMedia instanceof AdvancedTaxonImageInterface || self::zoneOf($existingMedia) !== $zone) {
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

    private static function zoneOf(AdvancedTaxonImageInterface $image): string
    {
        $type = $image->getType();

        return $type === null || $type === '' ? self::MAIN_IMAGE_ZONE : $type;
    }

    /**
     * Creates the media added to a zone by the "add" button, with its position pre-filled after the
     * highest position already used by that zone, so a new media is appended to the existing ones.
     */
    private function createPrefilledMedia(?AdvancedTaxonInterface $taxon, string $zone): AdvancedTaxonImageInterface
    {
        $highestPosition = null;

        foreach ($taxon?->getImages() ?? [] as $image) {
            if (!$image instanceof AdvancedTaxonImageInterface || self::zoneOf($image) !== $zone) {
                continue;
            }

            $position = $image->getPosition();
            if ($highestPosition === null || $position > $highestPosition) {
                $highestPosition = $position;
            }
        }

        $media = $this->taxonImageFactory->createNew();
        $media->setPosition($highestPosition === null ? 1 : $highestPosition + 1);

        return $media;
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [AdminTaxonType::class];
    }
}
