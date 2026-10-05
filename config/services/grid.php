<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Grid\ShopProductGridListener;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Grid\ShopProductListQueryBuilder;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Called by the Sylius shop product grid through an expression, hence public.
    $services->set(ShopProductGridListener::QUERY_BUILDER_SERVICE, ShopProductListQueryBuilder::class)
        ->args([
            service('sylius.repository.product'),
            service('cyllene_digital_sylius_advanced_taxon.facet.product_query_builder'),
        ])
        ->public();

    $services->set('cyllene_digital_sylius_advanced_taxon.grid.shop_product_listener', ShopProductGridListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.grid.shop_product']);
};
