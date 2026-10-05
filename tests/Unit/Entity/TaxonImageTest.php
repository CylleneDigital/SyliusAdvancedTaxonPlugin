<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        yield 'javascript scheme is rejected' => ['javascript:alert(1)', null];
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
}
