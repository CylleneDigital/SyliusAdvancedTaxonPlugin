<?php

declare(strict_types=1);

use Behat\Config\Config;

// One suite per feature, selected by its tag; the Sylius contexts are services of the test kernel.
return new Config([
    'default' => [
        'suites' => [
            'ui_customizing_taxon_appearance' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.locale',
                    'sylius.behat.context.transform.taxon',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.locale',
                    'sylius.behat.context.setup.taxonomy',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'sylius.behat.context.ui.admin.managing_taxons',
                    'sylius.behat.context.ui.admin.notification',
                    'sylius.behat.context.ui.save',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@customizing_taxon_appearance&&@ui',
                ],
            ],
            'ui_managing_taxon_media' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.locale',
                    'sylius.behat.context.transform.taxon',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.locale',
                    'sylius.behat.context.setup.taxonomy',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'sylius.behat.context.ui.admin.managing_taxons',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@managing_taxon_media&&@ui',
                ],
            ],
            'ui_configuring_taxon_pages' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.locale',
                    'sylius.behat.context.transform.taxon',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.locale',
                    'sylius.behat.context.setup.taxonomy',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'sylius.behat.context.ui.admin.managing_taxons',
                    'sylius.behat.context.ui.admin.notification',
                    'sylius.behat.context.ui.save',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@configuring_taxon_pages&&@ui',
                ],
            ],
            'ui_choosing_the_main_taxon_of_a_product' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.product',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.taxonomy',
                    'sylius.behat.context.setup.product',
                    'sylius.behat.context.setup.product_taxon',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'sylius.behat.context.ui.admin.managing_products',
                    'sylius.behat.context.ui.save',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@choosing_the_main_taxon_of_a_product&&@ui',
                ],
            ],
            'ui_managing_conditional_taxons' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.locale',
                    'sylius.behat.context.transform.taxon',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.locale',
                    'sylius.behat.context.setup.taxonomy',
                    'sylius.behat.context.setup.product',

                    'sylius.behat.context.ui.admin.managing_taxons',
                    'sylius.behat.context.ui.admin.notification',
                    'sylius.behat.context.ui.save',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.domain.materializing_conditional_taxons',
                ],
                'filters' => [
                    'tags' => '@managing_conditional_taxons&&@ui',
                ],
            ],
            'ui_enabling_the_mega_menu' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',
                    'sylius.behat.context.hook.session',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.channel',

                    'sylius.behat.context.setup.admin_security',
                    'sylius.behat.context.setup.channel',

                    'sylius.behat.context.ui.admin.managing_channels',
                    'sylius.behat.context.ui.admin.notification',
                    'sylius.behat.context.ui.save',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.admin.managing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@enabling_the_mega_menu&&@ui',
                ],
            ],
            'ui_browsing_taxon_pages' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.product',

                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.taxonomy',
                    'sylius.behat.context.setup.product',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.shop.browsing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@browsing_taxon_pages&&@ui',
                ],
            ],
            'ui_filtering_products_with_advanced_filters' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.product',

                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.taxonomy',
                    'sylius.behat.context.setup.product',
                    'sylius.behat.context.setup.product_attribute',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.shop.browsing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@filtering_products_with_advanced_filters&&@ui',
                ],
            ],
            'ui_browsing_universe_pages' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.product',

                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.taxonomy',
                    'sylius.behat.context.setup.product',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.shop.browsing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@browsing_universe_pages&&@ui',
                ],
            ],
            'ui_navigating_with_the_mega_menu' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.channel',

                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.taxonomy',
                    'cyllene_digital_sylius_advanced_taxon.behat.context.setup.advanced_taxon',

                    'cyllene_digital_sylius_advanced_taxon.behat.context.ui.shop.browsing_advanced_taxon',
                ],
                'filters' => [
                    'tags' => '@navigating_with_the_mega_menu&&@ui',
                ],
            ],
            'domain_materializing_conditional_taxons' => [
                'contexts' => [
                    'sylius.behat.context.hook.doctrine_orm',

                    'sylius.behat.context.transform.shared_storage',
                    'sylius.behat.context.transform.taxon',
                    'sylius.behat.context.transform.product',

                    'sylius.behat.context.setup.channel',
                    'sylius.behat.context.setup.product',
                    'sylius.behat.context.setup.product_taxon',

                    'cyllene_digital_sylius_advanced_taxon.behat.context.domain.materializing_conditional_taxons',
                ],
                'filters' => [
                    'tags' => '@materializing_conditional_taxons&&@domain',
                ],
            ],
        ],
    ],
]);
