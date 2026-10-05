<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Domain\MaterializingConditionalTaxonsContext;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Setup\AdvancedTaxonContext;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Admin\ManagingAdvancedTaxonContext;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Shop\BrowsingAdvancedTaxonContext;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Channel\MegaMenuFormElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Common\FormErrorsElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Taxon\AdvancedTaxonFormElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Shop\MenuElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Page\Shop\Taxon\IndexPage;

/*
 * Behat pages, elements and contexts, imported by tests/TestApplication/config/services_test.php in
 * the "test" environment. Behat resolves the contexts from the container, so they are public.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->public();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.page.shop.taxon.index', IndexPage::class)
        ->parent('sylius.behat.symfony_page')
        ->private();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.element.admin.taxon.form', AdvancedTaxonFormElement::class)
        ->parent('sylius.behat.element')
        ->private();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.element.admin.channel.mega_menu_form', MegaMenuFormElement::class)
        ->parent('sylius.behat.element')
        ->private();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.element.admin.form_errors', FormErrorsElement::class)
        ->parent('sylius.behat.element')
        ->private();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.element.shop.menu', MenuElement::class)
        ->parent('sylius.behat.element')
        ->private();

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon', AdvancedTaxonContext::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service('sylius.factory.taxon_image'),
            service(FilesystemAdapterInterface::class),
        ]);

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon', ManagingAdvancedTaxonContext::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.behat.element.admin.taxon.form'),
            service('cyllene_digital_sylius_advanced_taxon.behat.element.admin.channel.mega_menu_form'),
            service('cyllene_digital_sylius_advanced_taxon.behat.element.admin.form_errors'),
            service('doctrine.orm.default_entity_manager'),
            service(FilesystemAdapterInterface::class),
        ]);

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.context.ui.shop.browsing_advanced_taxon', BrowsingAdvancedTaxonContext::class)
        ->args([
            service('cyllene_digital_sylius_advanced_taxon.behat.page.shop.taxon.index'),
            service('sylius.behat.page.shop.home'),
            service('cyllene_digital_sylius_advanced_taxon.behat.element.shop.menu'),
        ]);

    $services->set('cyllene_digital_sylius_advanced_taxon.behat.context.domain.materializing_conditional_taxons', MaterializingConditionalTaxonsContext::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service('sylius.factory.taxon'),
            service('sylius.repository.taxon'),
            service('sylius.repository.product'),
            service('kernel'),
            service('cyllene_digital_sylius_advanced_taxon.conditional_taxon.sync_listener'),
            param('sylius.model.product_taxon.class'),
        ]);
};
