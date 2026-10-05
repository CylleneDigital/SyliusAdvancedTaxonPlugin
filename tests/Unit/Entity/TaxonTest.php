<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

final class TaxonTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function productMediaDisplayModeProvider(): iterable
    {
        yield 'stacked falls back to products_end' => ['stacked', Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END];
        yield 'random falls back to products_end' => ['random', Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END];
        yield 'products_start is kept' => [Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_START, Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_START];
        yield 'products_end is kept' => [Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END, Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END];
        yield 'products_random_middle is kept' => [Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE, Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE];
        yield 'unknown value falls back to products_end' => ['whatever', Taxon::MEDIA_RIGHT_PRODUCTS_POSITION_END];
    }

    #[DataProvider('productMediaDisplayModeProvider')]
    public function test_it_normalizes_the_product_media_display_mode(string $input, string $expected): void
    {
        $taxon = new Taxon();
        $taxon->setMediaDisplayModeProduct($input);

        self::assertSame($expected, $taxon->getMediaDisplayModeProduct());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function featuredProductsPositionProvider(): iterable
    {
        yield 'before_filters is kept' => [Taxon::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS, Taxon::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS];
        yield 'after_filters is kept' => [Taxon::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS, Taxon::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS];
        yield 'unknown value falls back to before_filters' => ['whatever', Taxon::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS];
    }

    #[DataProvider('featuredProductsPositionProvider')]
    public function test_it_normalizes_the_featured_products_position(string $input, string $expected): void
    {
        $taxon = new Taxon();
        $taxon->setFeaturedProductsPosition($input);

        self::assertSame($expected, $taxon->getFeaturedProductsPosition());
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function iconProvider(): iterable
    {
        yield 'uploaded pictogram is kept' => [Taxon::ICON_IMAGE_PREFIX . 'ab/cd/pictogram.png', Taxon::ICON_IMAGE_PREFIX . 'ab/cd/pictogram.png'];
        yield 'uploaded pictogram climbing out of the storage is rejected' => [Taxon::ICON_IMAGE_PREFIX . '../secret.png', null];
        yield 'local path is rejected' => ['/media/advanced-taxon/icons/pictogram.png', null];
        yield 'glyph name is kept' => ['tabler:home', 'tabler:home'];
        yield 'simple glyph name is kept' => ['home', 'home'];
        yield 'remote url is rejected' => ['https://example.com/icon.png', null];
        yield 'protocol relative url is rejected' => ['//example.com/icon.png', null];
        yield 'javascript scheme is rejected' => ['javascript:alert(1)', null];
        yield 'blank is rejected' => ['   ', null];
        yield 'null is rejected' => [null, null];
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function colorProvider(): iterable
    {
        yield 'hexadecimal color is kept' => ['#1A2b3C', '#1a2b3c'];
        yield 'empty color is cleared' => ['', null];
        yield 'named color is rejected' => ['red', null];
        yield 'css injection is rejected' => ['#000000; background: url(https://example.com)', null];
    }

    #[DataProvider('colorProvider')]
    public function test_it_only_keeps_an_hexadecimal_color(?string $input, ?string $expected): void
    {
        $taxon = new Taxon();
        $taxon->setColor($input);

        self::assertSame($expected, $taxon->getColor());
    }

    #[DataProvider('iconProvider')]
    public function test_it_sanitizes_the_icon(?string $input, ?string $expected): void
    {
        $taxon = new Taxon();
        $taxon->setIcon($input);

        self::assertSame($expected, $taxon->getIcon());
    }

    public function test_it_adds_and_removes_featured_children(): void
    {
        $parent = new Taxon();
        $child = new Taxon();

        $parent->addFeaturedChild($child);
        $parent->addFeaturedChild($child);

        self::assertCount(1, $parent->getFeaturedChildren());

        $parent->removeFeaturedChild($child);

        self::assertFalse($parent->getFeaturedChildren()->contains($child));
    }

    public function test_it_is_conditional_while_it_has_conditions(): void
    {
        $taxon = new Taxon();
        $condition = new FacetCondition();

        $taxon->addFacetCondition($condition);
        self::assertTrue($taxon->isConditional());

        $taxon->removeFacetCondition($condition);
        self::assertFalse($taxon->isConditional());
    }

    public function test_it_falls_back_to_the_stacked_media_display_mode(): void
    {
        $taxon = new Taxon();
        $taxon->setMediaDisplayModeTop('random');
        $taxon->setMediaDisplayModeLeft('whatever');

        self::assertSame('random', $taxon->getMediaDisplayModeTop());
        self::assertSame('stacked', $taxon->getMediaDisplayModeLeft());
    }

    public function test_only_the_top_and_bottom_zones_can_be_shown_as_a_slider(): void
    {
        $taxon = new Taxon();
        $taxon->setMediaDisplayModeTop('slider');
        $taxon->setMediaDisplayModeBottom('slider');
        $taxon->setMediaDisplayModeLeft('slider');
        $taxon->setMediaDisplayModeFeatured('slider');

        self::assertSame('slider', $taxon->getMediaDisplayModeTop());
        self::assertSame('slider', $taxon->getMediaDisplayModeBottom());
        self::assertSame('stacked', $taxon->getMediaDisplayModeLeft());
        self::assertSame('stacked', $taxon->getMediaDisplayModeFeatured());
    }

    public function test_it_creates_the_featured_items_translation_of_a_missing_locale(): void
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->getOrCreateFeaturedItemsTranslation('en_US')->setFeaturedProductsTitle('Staff picks');

        $taxon->getOrCreateFeaturedItemsTranslation('fr_FR')->setFeaturedProductsTitle('Coups de cœur');

        self::assertSame('Staff picks', $taxon->getFeaturedProductsTitle('en_US'));
        self::assertSame('Coups de cœur', $taxon->getFeaturedProductsTitle('fr_FR'));
    }

    public function test_it_indexes_featured_items_translations_by_locale(): void
    {
        $taxon = new Taxon();
        $translation = $taxon->getOrCreateFeaturedItemsTranslation('en_US');
        $translation->setUniversePageTitle('Universe');

        self::assertSame('en_US', $translation->getLocale());
        self::assertSame('Universe', $taxon->getUniversePageTitle('en_US'));
        self::assertSame($translation, $taxon->getOrCreateFeaturedItemsTranslation('en_US'));
        self::assertSame($translation, $taxon->getFeaturedItemsTranslations()->get('en_US'));
    }

    public function test_the_featured_items_texts_fall_back_from_the_locale_to_the_current_then_the_fallback_one(): void
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale('fr_FR');
        $taxon->setFallbackLocale('en_US');
        $taxon->getOrCreateFeaturedItemsTranslation('en_US')->setFeaturedProductsTitle('Our picks');
        $taxon->getOrCreateFeaturedItemsTranslation('fr_FR')->setFeaturedProductsTitle('Notre sélection');

        self::assertSame('Our picks', $taxon->getFeaturedProductsTitle('en_US'));
        self::assertSame('Notre sélection', $taxon->getFeaturedProductsTitle('de_DE'));
        self::assertSame('Notre sélection', $taxon->getFeaturedProductsTitle());

        $taxon->setCurrentLocale('de_DE');
        self::assertSame('Our picks', $taxon->getFeaturedProductsTitle('it_IT'));

        $taxon->setFallbackLocale('it_IT');
        self::assertNull($taxon->getFeaturedItemsTranslation('es_ES'));
    }
}
