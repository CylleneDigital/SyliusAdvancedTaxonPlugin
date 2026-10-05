<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\AdvancedTaxonExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\FacetRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\IconLibrariesRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\MenuRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\UniverseRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\VisibilityRuntime;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.extension', AdvancedTaxonExtension::class)
        ->tag('twig.extension');

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.runtime.visibility', VisibilityRuntime::class)
        ->args([
            service('sylius.context.channel'),
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product.class'),
        ])
        ->tag('twig.runtime')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.runtime.facet', FacetRuntime::class)
        ->args([
            service('sylius.repository.taxon'),
            service('cyllene_digital_sylius_advanced_taxon.facet.product_query_builder'),
            service('request_stack'),
            service('sylius.context.locale'),
            param('sylius_shop.product_grid.include_all_descendants'),
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product_attribute.class'),
            param('sylius.model.product_option.class'),
            param('sylius.model.taxon.class'),
        ])
        ->tag('twig.runtime')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.runtime.icon_libraries', IconLibrariesRuntime::class)
        ->args([param('cyllene_digital_sylius_advanced_taxon.icon_libraries')])
        ->tag('twig.runtime');

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.runtime.menu', MenuRuntime::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.taxon.class'),
        ])
        ->tag('twig.runtime');

    $services->set('cyllene_digital_sylius_advanced_taxon.twig.runtime.universe', UniverseRuntime::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.twig.runtime.visibility'),
            service('liip_imagine.cache.manager'),
            service('liip_imagine.filter.configuration'),
        ])
        ->tag('twig.runtime');
};
