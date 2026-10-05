<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Controller\AdminFacetPreviewController;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(AdminFacetPreviewController::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.facet.conditional_taxon_query_builder'),
            service('sylius.translation_locale_provider'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->tag('controller.service_arguments');
};
