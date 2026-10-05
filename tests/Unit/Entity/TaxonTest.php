<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        yield 'local pictogram path is kept' => [Taxon::ICON_PATH_PREFIX . '/pictogram.png', Taxon::ICON_PATH_PREFIX . '/pictogram.png'];
        yield 'local path outside the pictogram directory is rejected' => ['/uploads/advanced-taxon/icons/pictogram.png', null];
        yield 'glyph name is kept' => ['tabler:home', 'tabler:home'];
        yield 'simple glyph name is kept' => ['home', 'home'];
        yield 'remote url is rejected' => ['https://example.com/icon.png', null];
        yield 'protocol relative url is rejected' => ['//example.com/icon.png', null];
        yield 'javascript scheme is rejected' => ['javascript:alert(1)', null];
        yield 'blank is rejected' => ['   ', null];
        yield 'null is rejected' => [null, null];
    }

    #[DataProvider('iconProvider')]
    public function test_it_sanitizes_the_icon(?string $input, ?string $expected): void
    {
        $taxon = new Taxon();
        $taxon->setIcon($input);

        self::assertSame($expected, $taxon->getIcon());
    }

    public function test_it_keeps_featured_children_and_back_references_in_sync(): void
    {
        $parent = new Taxon();
        $child = new Taxon();

        $parent->addFeaturedChild($child);

        self::assertTrue($parent->getFeaturedChildren()->contains($child));
        self::assertTrue($child->getFeaturedBy()->contains($parent));

        $parent->removeFeaturedChild($child);

        self::assertFalse($parent->getFeaturedChildren()->contains($child));
        self::assertFalse($child->getFeaturedBy()->contains($parent));
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
}
