<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Form;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonAppearanceTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonMediaZonesTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonMediaType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Form\Extension\CollectionTypeExtension;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;
use Symfony\UX\Icons\IconRendererInterface;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Fixture\TaxonImageFactory;

/**
 * The taxon form as the three plugin extensions build it.
 */
final class TaxonTypeExtensionsTest extends TestCase
{
    /**
     * Every advanced media zone exposes its media collection, and every zone rendered with a
     * display mode also exposes that display mode field.
     */
    public function test_it_registers_every_advanced_media_zone(): void
    {
        $builder = $this->createBuilder();

        $zonesWithDisplayMode = ['Top', 'Bottom', 'Left', 'Product', 'Featured'];

        foreach (['Main', ...$zonesWithDisplayMode, 'SliderUniverse'] as $zone) {
            self::assertTrue($builder->has('media' . $zone), sprintf('Missing "media%s" field.', $zone));
        }

        foreach ($zonesWithDisplayMode as $zone) {
            self::assertTrue($builder->has('mediaDisplayMode' . $zone), sprintf('Missing "mediaDisplayMode%s" field.', $zone));
        }

        // The main image and the universe slider have a single rendering, so no display mode field.
        self::assertFalse($builder->has('mediaDisplayModeMain'));
        self::assertFalse($builder->has('mediaDisplayModeSliderUniverse'));
        self::assertSame('cyllene_digital_sylius_advanced_taxon.form.media.zones.featured.display_mode', $builder->get('mediaDisplayModeFeatured')->getOptions()['label']);
        self::assertTrue($builder->has('universeBackgroundUseTaxonColor'));
        self::assertTrue($builder->has('universeTitleBackgroundEnabled'));
    }

    public function test_it_only_offers_a_destination_url_in_the_zones_rendering_links(): void
    {
        $builder = $this->createBuilder();

        foreach (['Main', 'Top', 'Bottom', 'Left', 'Featured'] as $zone) {
            self::assertFalse(
                $this->getMediaPrototype($builder, $zone)->has('url'),
                sprintf('The "%s" zone must not offer a destination URL.', $zone),
            );
        }

        self::assertTrue($this->getMediaPrototype($builder, 'Product')->has('url'));
        self::assertTrue($this->getMediaPrototype($builder, 'SliderUniverse')->has('url'));
    }

    /**
     * The card text toggle only drives the product-like cards injected into the product listing,
     * so only the right (product card) zone may offer it.
     */
    public function test_it_only_offers_the_card_text_toggle_for_the_product_card_zone(): void
    {
        $builder = $this->createBuilder();

        foreach (['Top', 'Bottom', 'Left', 'Featured', 'SliderUniverse'] as $zone) {
            self::assertFalse(
                $this->getMediaPrototype($builder, $zone)->has('showCardText'),
                sprintf('The "%s" zone must not offer the card text toggle.', $zone),
            );
        }

        self::assertTrue($this->getMediaPrototype($builder, 'Product')->has('showCardText'));
    }

    /**
     * A media added to a zone is pre-filled with the highest position after the existing ones.
     */
    public function test_it_prefills_the_next_position_of_every_zone(): void
    {
        $taxon = new Taxon();
        $taxon->addImage($this->createMedia('top', 3));
        $taxon->addImage($this->createMedia('right', 12));
        $taxon->addImage($this->createMedia('slider_universe', 1));

        $builder = $this->createBuilder($taxon);

        self::assertSame(4, $this->getMediaPrototype($builder, 'Top')->get('position')->getData());
        self::assertSame(13, $this->getMediaPrototype($builder, 'Product')->get('position')->getData());
        self::assertSame(2, $this->getMediaPrototype($builder, 'SliderUniverse')->get('position')->getData());
        // A zone without media starts at the first position.
        self::assertSame(1, $this->getMediaPrototype($builder, 'Bottom')->get('position')->getData());
    }

    /**
     * The pre-filled position must never hide the position stored on an existing media.
     */
    public function test_it_keeps_the_stored_position_of_an_existing_media(): void
    {
        $taxon = new Taxon();
        $taxon->addImage($this->createMedia('right', 5));

        $builder = $this->createBuilder($taxon);
        $builder->get('mediaProduct')->setData($taxon->getImages()->toArray());

        $mediaForm = $builder->get('mediaProduct')->getForm();
        self::assertSame(5, $mediaForm->get('0')->get('position')->getData());
    }

    /**
     * Sylius stores the main taxon image without type: it belongs to the main image zone.
     */
    public function test_it_counts_an_image_without_type_in_the_main_image_zone(): void
    {
        $taxon = new Taxon();
        $legacyImage = new TaxonImage();
        $legacyImage->setPosition(4);
        $taxon->addImage($legacyImage);
        $taxon->addImage($this->createMedia('main', 2));
        $taxon->addImage($this->createMedia('top', 9));

        self::assertSame(5, $this->getMediaPrototype($this->createBuilder($taxon), 'Main')->get('position')->getData());
    }

    public function test_it_registers_conditional_and_featured_fields(): void
    {
        $builder = $this->createBuilder();

        self::assertTrue($builder->has('facetConditions'));
        self::assertTrue($builder->has('featuredChildren'));
        self::assertTrue($builder->has('featuredProducts'));
        self::assertTrue($builder->has('featuredItemsTranslations'));
        self::assertTrue($builder->has('includeChildrenProducts'));
        self::assertTrue($builder->has('advancedFiltersEnabled'));
    }

    private function createMedia(string $type, int $position): TaxonImage
    {
        $media = new TaxonImage();
        $media->setType($type);
        $media->setPosition($position);

        return $media;
    }

    /**
     * Returns the prototype form of a media zone, which is what gets added to the taxon form.
     */
    private function getMediaPrototype(FormBuilderInterface $builder, string $zone): FormInterface
    {
        $prototype = $builder->get('media' . $zone)->getAttribute('prototype');

        if (!$prototype instanceof FormInterface) {
            self::fail(sprintf('The "%s" zone must expose a media prototype.', $zone));
        }

        return $prototype;
    }

    private function createBuilder(?Taxon $taxon = null): FormBuilderInterface
    {
        $localeProvider = $this->createStub(TranslationLocaleProviderInterface::class);
        $localeProvider->method('getDefinedLocalesCodes')->willReturn(['en_US']);
        $localeProvider->method('getDefaultLocaleCode')->willReturn('en_US');

        $factory = Forms::createFormFactoryBuilder()
            // The media entry type relies on the translations type, which needs the locale provider.
            ->addExtension(new PreloadedExtension(
                [new ResourceTranslationsType($localeProvider), new TaxonMediaType(TaxonImage::class)],
                [CollectionType::class => [new CollectionTypeExtension()]],
            ))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $builder = $factory->createBuilder(FormType::class, $taxon ?? new Taxon());

        $extensions = [
            new TaxonTypeExtension($localeProvider),
            new TaxonAppearanceTypeExtension(new TaxonIconUploader($this->createStub(ImageUploaderInterface::class)), new IdentityTranslator(), $this->createStub(IconRendererInterface::class)),
            new TaxonMediaZonesTypeExtension(new UploadedImageResizer(), new TaxonImageFactory()),
        ];
        foreach ($extensions as $extension) {
            $extension->buildForm($builder, []);
        }

        return $builder;
    }
}
