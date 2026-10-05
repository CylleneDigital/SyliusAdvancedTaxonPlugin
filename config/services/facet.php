<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonQueryBuilder;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceChecker;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceCheckerInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\SelectAttributeValueResolver;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints\ValidFacetConditionValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.facet.reference_checker', FacetReferenceChecker::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product_attribute.class'),
            param('sylius.model.product_option.class'),
            param('sylius.model.taxon.class'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(FacetReferenceCheckerInterface::class, 'cyllene_digital_sylius_advanced_taxon.facet.reference_checker');

    $services->set('cyllene_digital_sylius_advanced_taxon.facet.select_attribute_values', SelectAttributeValueResolver::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product_attribute_value.class'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('cyllene_digital_sylius_advanced_taxon.facet.conditional_taxon_query_builder', ConditionalTaxonQueryBuilder::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product.class'),
            service('cyllene_digital_sylius_advanced_taxon.facet.reference_checker'),
            service('cyllene_digital_sylius_advanced_taxon.facet.select_attribute_values'),
        ]);

    $services->set('cyllene_digital_sylius_advanced_taxon.facet.product_query_builder', FacetProductQueryBuilder::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.product.class'),
            service('sylius.context.channel'),
            service('cyllene_digital_sylius_advanced_taxon.facet.select_attribute_values'),
        ]);

    $services->set('cyllene_digital_sylius_advanced_taxon.validator.valid_facet_condition', ValidFacetConditionValidator::class)
        ->args([service('cyllene_digital_sylius_advanced_taxon.facet.reference_checker')])
        ->tag('validator.constraint_validator');
};
