<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cyllene_digital_sylius_advanced_taxon');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('icon_libraries')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->stringNode('label')->defaultNull()->end()
                            ->stringNode('icon_prefix')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('icons')
                                ->stringPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
