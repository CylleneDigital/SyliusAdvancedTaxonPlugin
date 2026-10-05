<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Form;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

final class TaxonTypeExtensionTest extends TestCase
{
    public function test_it_registers_the_universe_media_zone_field(): void
    {
        $builder = $this->createBuilder();

        self::assertTrue($builder->has('mediaSliderUnivers'));
        self::assertFalse($builder->has('mediaDisplayModeSliderUnivers'));
        self::assertTrue($builder->has('mediaFeatured'));
        self::assertTrue($builder->has('mediaDisplayModeFeatured'));
        self::assertTrue($builder->has('universeBackgroundUseTaxonColor'));
        self::assertTrue($builder->has('universeTitleBackgroundEnabled'));
        self::assertSame('cyllene_digital_sylius_advanced_taxon.form.media.zones.featured.display_mode', $builder->get('mediaDisplayModeFeatured')->getOptions()['label']);
    }

    /**
     * Every advanced media zone exposes its media collection, and every zone rendered with a
     * display mode also exposes that display mode field.
     */
    public function test_it_registers_every_advanced_media_zone(): void
    {
        $builder = $this->createBuilder();

        $zonesWithDisplayMode = ['Top', 'Bottom', 'Left', 'Product', 'Featured'];

        foreach ([...$zonesWithDisplayMode, 'SliderUnivers'] as $zone) {
            self::assertTrue($builder->has('media' . $zone), sprintf('Missing "media%s" field.', $zone));
        }

        foreach ($zonesWithDisplayMode as $zone) {
            self::assertTrue($builder->has('mediaDisplayMode' . $zone), sprintf('Missing "mediaDisplayMode%s" field.', $zone));
        }

        // The universe slider always renders as a slider, so it has no display mode field.
        self::assertFalse($builder->has('mediaDisplayModeSliderUnivers'));
    }

    /**
     * The card text toggle only drives the product-like cards injected into the product listing,
     * so only the right (product card) zone may offer it.
     */
    public function test_it_only_offers_the_card_text_toggle_for_the_product_card_zone(): void
    {
        $builder = $this->createBuilder();

        foreach (['Top', 'Bottom', 'Left', 'Featured', 'SliderUnivers'] as $zone) {
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
        $taxon->addImage($this->createMedia('slider_univers', 1));

        $builder = $this->createBuilder($taxon);

        self::assertSame(4, $this->getMediaPrototype($builder, 'Top')->get('position')->getData());
        self::assertSame(13, $this->getMediaPrototype($builder, 'Product')->get('position')->getData());
        self::assertSame(2, $this->getMediaPrototype($builder, 'SliderUnivers')->get('position')->getData());
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
        $localeProvider = $this->createMock(TranslationLocaleProviderInterface::class);
        $localeProvider->method('getDefinedLocalesCodes')->willReturn(['en_US']);
        $localeProvider->method('getDefaultLocaleCode')->willReturn('en_US');

        $factory = Forms::createFormFactoryBuilder()
            // The media entry type relies on the translations type, which needs the locale provider.
            ->addExtension(new PreloadedExtension([new ResourceTranslationsType($localeProvider)], []))
            ->getFormFactory();

        $builder = $factory->createBuilder(FormType::class, $taxon ?? new Taxon());

        $extension = new TaxonTypeExtension(
            new UploadedImageResizer(),
            $localeProvider,
            new TaxonIconUploader(sys_get_temp_dir()),
        );
        $extension->buildForm($builder, []);

        return $builder;
    }
}
