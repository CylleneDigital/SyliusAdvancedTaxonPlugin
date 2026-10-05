<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\FacetRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\IconLibrariesRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\MenuRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\UniverseRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\VisibilityRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Declares the plugin Twig functions; their runtimes are only built when a template calls them.
 */
final class AdvancedTaxonExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('advanced_taxon_facet_choices', [FacetRuntime::class, 'getFacetChoices']),
            new TwigFunction('advanced_taxon_available_filters', [FacetRuntime::class, 'getAvailableFilters']),
            new TwigFunction('advanced_taxon_taxon_from_request', [FacetRuntime::class, 'getTaxonFromRequest']),
            new TwigFunction('advanced_taxon_icon_libraries', [IconLibrariesRuntime::class, 'getIconLibraries']),
            new TwigFunction('advanced_taxon_ordered_children', [MenuRuntime::class, 'getOrderedChildren']),
            new TwigFunction('advanced_taxon_preload_menu', [MenuRuntime::class, 'preloadMenu']),
            new TwigFunction('advanced_taxon_preload_universe', [MenuRuntime::class, 'preloadUniverse']),
            new TwigFunction('advanced_taxon_universe_data', [UniverseRuntime::class, 'getUniverseData']),
            new TwigFunction('advanced_taxon_universe_slider_media', [UniverseRuntime::class, 'getUniverseSliderMedia']),
            new TwigFunction('advanced_taxon_universe_bottom_media', [UniverseRuntime::class, 'getUniverseBottomMedia']),
            new TwigFunction('advanced_taxon_media_url', [UniverseRuntime::class, 'getMediaUrl']),
            new TwigFunction('advanced_taxon_media_mobile_url', [UniverseRuntime::class, 'getMediaMobileUrl']),
            new TwigFunction('advanced_taxon_media_href', [UniverseRuntime::class, 'getMediaHref']),
            new TwigFunction('advanced_taxon_universe_child_card_data', [UniverseRuntime::class, 'getUniverseChildCardData']),
            new TwigFunction('advanced_taxon_featured_media_url', [UniverseRuntime::class, 'getFeaturedMediaUrl']),
            new TwigFunction('advanced_taxon_has_featured_children_products', [UniverseRuntime::class, 'hasFeaturedChildrenProducts']),
            new TwigFunction('advanced_taxon_visible_products', [VisibilityRuntime::class, 'visibleProducts']),
            new TwigFunction('advanced_taxon_visible_taxons', [VisibilityRuntime::class, 'visibleTaxons']),
        ];
    }
}
