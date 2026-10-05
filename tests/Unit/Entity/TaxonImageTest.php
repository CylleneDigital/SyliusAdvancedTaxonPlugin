<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImageTranslation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;

final class TaxonImageTest extends TestCase
{
    /**
     * The destination URL is rendered inside an "href" attribute on the storefront, so only http(s)
     * URLs and same-origin relative paths may be stored.
     *
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function destinationUrlProvider(): iterable
    {
        yield 'absolute https url is kept' => ['https://example.com/product', 'https://example.com/product'];
        yield 'absolute http url is kept' => ['http://example.com/product', 'http://example.com/product'];
        yield 'relative path is kept' => ['/products/foo', '/products/foo'];
        yield 'surrounding whitespace is trimmed' => ['  /products/foo  ', '/products/foo'];
        yield 'protocol relative url is rejected' => ['//evil.example.com', null];
        yield 'backslash host url is rejected' => ['/\\evil.example.com', null];
        yield 'backslash in a relative path is rejected' => ['/products\\..\\evil', null];
        yield 'javascript scheme is rejected' => ['javascript:alert(1)', null];
        yield 'tab turning a path into another host is rejected' => ["/\t/evil.example.com", null];
        yield 'line break turning a path into another host is rejected' => ["/\n/evil.example.com", null];
        yield 'control character in an absolute url is rejected' => ["https://exa\x00mple.com/", null];
        yield 'data uri is rejected' => ['data:text/html,<script>alert(1)</script>', null];
        yield 'blank is rejected' => ['', null];
        yield 'null is rejected' => [null, null];
    }

    #[DataProvider('destinationUrlProvider')]
    public function test_it_sanitizes_the_destination_url(?string $input, ?string $expected): void
    {
        $media = new TaxonImage();
        $media->setUrl($input);

        self::assertSame($expected, $media->getUrl());
    }

    public function test_the_texts_fall_back_from_the_locale_to_the_current_then_the_fallback_one(): void
    {
        $media = new TaxonImage();
        $media->setCurrentLocale('fr_FR');
        $media->setFallbackLocale('en_US');
        $media->addTranslation($this->translation('en_US', 'Title', 'Description'));
        $media->addTranslation($this->translation('fr_FR', 'Titre', 'Texte'));

        self::assertSame('Title', $media->getTitleForLocale('en_US'));
        self::assertSame('Titre', $media->getTitleForLocale('de_DE'));
        self::assertSame('Texte', $media->getDescriptionForLocale(null));

        $media->setCurrentLocale('de_DE');
        self::assertSame('Title', $media->getTitleForLocale('it_IT'));
        self::assertSame('Description', $media->getDescriptionForLocale('it_IT'));
    }

    public function test_an_empty_translated_text_falls_back_to_the_untranslated_one(): void
    {
        // Saved before the media texts were translatable: the text sits on the media itself.
        $media = new TaxonImage();
        $media->setTitle('Legacy title');
        $media->setDescription('Legacy description');
        $media->setCurrentLocale('en_US');
        $media->setFallbackLocale('en_US');
        $media->addTranslation($this->translation('en_US', '', null));

        self::assertSame('Legacy title', $media->getTitleForLocale('en_US'));
        self::assertSame('Legacy description', $media->getDescriptionForLocale('en_US'));
    }

    private function translation(string $locale, ?string $title, ?string $description): TaxonImageTranslation
    {
        $translation = new TaxonImageTranslation();
        $translation->setLocale($locale);
        $translation->setTitle($title);
        $translation->setDescription($description);

        return $translation;
    }
}
