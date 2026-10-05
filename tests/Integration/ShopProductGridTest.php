<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Grid\ShopProductGridListener;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class ShopProductGridTest extends KernelTestCase
{
    public function test_the_shop_product_grid_is_wired_to_the_plugin_query(): void
    {
        $provider = self::getContainer()->get(GridProviderInterface::class);
        Assert::isInstanceOf($provider, GridProviderInterface::class);

        $repository = $provider->get('sylius_shop_product')->getDriverConfiguration()['repository'] ?? null;
        Assert::isArray($repository);

        self::assertSame(
            [sprintf("expr:service('%s')", ShopProductGridListener::QUERY_BUILDER_SERVICE), 'createListQueryBuilder'],
            $repository['method'],
        );
        Assert::isArray($repository['arguments']);
        self::assertSame(
            ['channel', 'taxon', 'locale', 'sorting', 'includeAllDescendants', 'advancedFilters'],
            array_keys($repository['arguments']),
        );
        self::assertTrue(self::getContainer()->has(ShopProductGridListener::QUERY_BUILDER_SERVICE));
    }
}
