<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\ChannelTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonAppearanceTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonMediaZonesTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension\TaxonTypeExtension;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\ChildTaxonAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\TaxonAttachedProductAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonMediaType;
use Sylius\Bundle\AdminBundle\Form\Type\ChannelType;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType;
use Symfony\UX\Icons\IconRendererInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.form.extension.taxon', TaxonTypeExtension::class)
        ->args([service('sylius.translation_locale_provider')])
        ->tag('form.type_extension', ['extended_type' => TaxonType::class]);

    $services->set('cyllene_digital_sylius_advanced_taxon.form.extension.taxon_appearance', TaxonAppearanceTypeExtension::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.uploader.taxon_icon'),
            service('translator'),
            service(IconRendererInterface::class),
        ])
        ->tag('form.type_extension', ['extended_type' => TaxonType::class]);

    $services->set('cyllene_digital_sylius_advanced_taxon.form.extension.taxon_media_zones', TaxonMediaZonesTypeExtension::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.uploader.image_resizer'),
            service('sylius.factory.taxon_image'),
        ])
        ->tag('form.type_extension', ['extended_type' => TaxonType::class]);

    $services->set('cyllene_digital_sylius_advanced_taxon.form.extension.channel', ChannelTypeExtension::class)
        ->tag('form.type_extension', ['extended_type' => ChannelType::class]);

    $services->set('cyllene_digital_sylius_advanced_taxon.form.type.media', TaxonMediaType::class)
        ->args([param('sylius.model.taxon_image.class')])
        ->tag('form.type');

    // The aliases and the route come from the #[AsEntityAutocompleteField] attribute of each type.
    $services->set('cyllene_digital_sylius_advanced_taxon.form.type.child_taxon_autocomplete', ChildTaxonAutocompleteType::class)
        ->args([param('sylius.model.taxon.class')])
        ->tag('form.type')
        ->tag('ux.entity_autocomplete_field');

    $services->set('cyllene_digital_sylius_advanced_taxon.form.type.attached_product_autocomplete', TaxonAttachedProductAutocompleteType::class)
        ->args([param('sylius.model.product.class')])
        ->tag('form.type')
        ->tag('ux.entity_autocomplete_field');
};
