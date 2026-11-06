<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonTreePresenter;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Component\Taxon\TreeComponent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.taxon_tree_presenter', TaxonTreePresenter::class);

    // Same arguments as the Sylius component it extends, plus the presenter: the tree hook renders
    // this one so that the search field can re-render the tree data.
    $services->set('cyllene_digital_sylius_advanced_taxon.twig.component.taxon.tree', TreeComponent::class)
        ->args([
            service('sylius_admin.doctrine.query.taxon.all_taxons'),
            service('sylius.repository.taxon'),
            service('sylius.manager.taxon'),
            service('cyllene_digital_sylius_advanced_taxon.taxon_tree_presenter'),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'cyllene_digital_sylius_advanced_taxon:taxon:tree']);
};
