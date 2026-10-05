<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Grid;

use Sylius\Component\Grid\Event\GridDefinitionConverterEvent;

/**
 * Routes the query of the Sylius shop product grid through {@see ShopProductListQueryBuilder}, so
 * that the taxon options of the plugin (children products, advanced filters) apply to the native
 * listing instead of a parallel, unpaginated one.
 */
final readonly class ShopProductGridListener
{
    public const string QUERY_BUILDER_SERVICE = 'cyllene_digital_sylius_advanced_taxon.grid.shop_product_list_query_builder';

    public function __invoke(GridDefinitionConverterEvent $event): void
    {
        $grid = $event->getGrid();
        $driverConfiguration = $grid->getDriverConfiguration();

        /** @var array{method?: mixed, arguments?: array<string, mixed>} $repository */
        $repository = $driverConfiguration['repository'] ?? [];

        // Only the native taxon listing is taken over: an application that already replaced the
        // repository method keeps its own query.
        if (($repository['method'] ?? null) !== 'createShopListQueryBuilder') {
            return;
        }

        $repository['method'] = [sprintf("expr:service('%s')", self::QUERY_BUILDER_SERVICE), 'createListQueryBuilder'];
        $repository['arguments'] = [
            ...$repository['arguments'] ?? [],
            'advancedFilters' => "expr:service('request_stack').getCurrentRequest().query.all('advanced')",
        ];

        $driverConfiguration['repository'] = $repository;
        $grid->setDriverConfiguration($driverConfiguration);
    }
}
