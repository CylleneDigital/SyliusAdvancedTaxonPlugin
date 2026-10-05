<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener\TaxonIconRemovalListener;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use Doctrine\ORM\Events;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.uploader.taxon_icon', TaxonIconUploader::class)
        ->args([service('sylius.uploader.image')]);

    $services->set('cyllene_digital_sylius_advanced_taxon.uploader.image_resizer', UploadedImageResizer::class);

    $services->set('cyllene_digital_sylius_advanced_taxon.listener.taxon_icon_removal', TaxonIconRemovalListener::class)
        ->args([service('cyllene_digital_sylius_advanced_taxon.uploader.taxon_icon')])
        ->tag('doctrine.event_listener', ['event' => Events::preUpdate])
        ->tag('doctrine.event_listener', ['event' => Events::postRemove])
        ->tag('doctrine.event_listener', ['event' => Events::postFlush])
        ->tag('kernel.reset', ['method' => 'reset']);
};
