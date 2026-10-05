<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Command\SyncConditionalTaxonsCommand;
use CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener\ConditionalTaxonSyncListener;
use CylleneDigital\SyliusAdvancedTaxonPlugin\MessageHandler\SynchronizeConditionalTaxonHandler;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonProductAssigner;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonSynchronizerInterface;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_advanced_taxon.conditional_taxon.synchronizer', ConditionalTaxonProductAssigner::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('cyllene_digital_sylius_advanced_taxon.facet.conditional_taxon_query_builder'),
            service('sylius.repository.taxon'),
            service('sylius.factory.product_taxon'),
            service('sylius.translation_locale_provider'),
            param('sylius.model.product_taxon.class'),
            param('sylius.model.product.class'),
        ]);
    $services->alias(ConditionalTaxonSynchronizerInterface::class, 'cyllene_digital_sylius_advanced_taxon.conditional_taxon.synchronizer');

    $services->set('cyllene_digital_sylius_advanced_taxon.conditional_taxon.sync_listener', ConditionalTaxonSyncListener::class)
        ->args([service('sylius.command_bus')])
        ->tag('doctrine.event_listener', ['event' => Events::onFlush])
        ->tag('doctrine.event_listener', ['event' => Events::postFlush])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'dispatchPending', 'priority' => -1024])
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'dispatchPending'])
        ->tag('kernel.event_listener', ['event' => WorkerMessageHandledEvent::class, 'method' => 'dispatchPending'])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('cyllene_digital_sylius_advanced_taxon.conditional_taxon.sync_handler', SynchronizeConditionalTaxonHandler::class)
        ->args([
            service('sylius.repository.taxon'),
            service('cyllene_digital_sylius_advanced_taxon.conditional_taxon.synchronizer'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('cyllene_digital_sylius_advanced_taxon.command.sync_conditional_taxons', SyncConditionalTaxonsCommand::class)
        ->args([service('cyllene_digital_sylius_advanced_taxon.conditional_taxon.synchronizer')])
        ->tag('console.command');
};
