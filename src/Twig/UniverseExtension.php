<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AutoconfigureTag('twig.extension')]
final class UniverseExtension extends AbstractExtension
{
    public function __construct(
        private readonly Packages $packages,
        private readonly CacheManager $cacheManager,
        #[Autowire(service: 'liip_imagine.filter.configuration')]
        private readonly FilterConfiguration $filterConfiguration,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('advanced_taxon_universe_data', [$this, 'getUniverseData']),
            new TwigFunction('advanced_taxon_universe_slider_media', [$this, 'getUniverseSliderMedia']),
            new TwigFunction('advanced_taxon_universe_bottom_media', [$this, 'getUniverseBottomMedia']),
            new TwigFunction('advanced_taxon_media_url', [$this, 'getMediaUrl']),
            new TwigFunction('advanced_taxon_media_mobile_url', [$this, 'getMediaMobileUrl']),
            new TwigFunction('advanced_taxon_media_href', [$this, 'getMediaHref']),
            new TwigFunction('advanced_taxon_universe_child_card_data', [$this, 'getUniverseChildCardData']),
            new TwigFunction('advanced_taxon_featured_media_url', [$this, 'getFeaturedMediaUrl']),
            new TwigFunction('advanced_taxon_has_featured_children_products', [$this, 'hasFeaturedChildrenProducts']),
        ];
    }

    /**
     * @return array{
     *     useTaxonColorBackground: bool,
     *     titleBackgroundEnabled: bool,
     *     universeButtonColor: string,
     *     universeAccentColor: string,
     *     universePageTitle: string,
     *     universePageDescription: ?string,
     *     universeFeaturedTitle: ?string,
     *     universeFeaturedDescription: ?string
     * }
     */
    public function getUniverseData(TaxonInterface $taxon): array
    {
        $color = $taxon instanceof Taxon ? $taxon->getColor() : null;
        $hasColor = is_string($color) && $color !== '';

        $universePageTitle = $taxon instanceof Taxon ? $taxon->getUniversePageTitle() : null;
        $universePageDescription = $taxon instanceof Taxon ? $taxon->getUniversePageDescription() : null;
        $universeFeaturedTitle = $taxon instanceof Taxon ? $taxon->getUniverseFeaturedProductsTitle() : null;
        $universeFeaturedDescription = $taxon instanceof Taxon ? $taxon->getUniverseFeaturedProductsDescription() : null;

        return [
            'useTaxonColorBackground' => $hasColor && $taxon instanceof Taxon && $taxon->isUniverseBackgroundUseTaxonColor(),
            'titleBackgroundEnabled' => $hasColor && $taxon instanceof Taxon && $taxon->isUniverseTitleBackgroundEnabled(),
            'universeButtonColor' => $hasColor ? $color : '#0d6efd',
            'universeAccentColor' => $hasColor ? $color : '#ffffff',
            'universePageTitle' => (is_string($universePageTitle) && $universePageTitle !== '') ? $universePageTitle : (string) $taxon->getName(),
            'universePageDescription' => is_string($universePageDescription) ? $universePageDescription : null,
            'universeFeaturedTitle' => is_string($universeFeaturedTitle) ? $universeFeaturedTitle : null,
            'universeFeaturedDescription' => is_string($universeFeaturedDescription) ? $universeFeaturedDescription : null,
        ];
    }

    /**
     * @return array<int, TaxonImage>
     */
    public function getUniverseSliderMedia(TaxonInterface $taxon): array
    {
        return $this->getTaxonImagesByTypes($taxon, ['slider_univers']);
    }

    /**
     * @return array<int, TaxonImage>
     */
    public function getUniverseBottomMedia(TaxonInterface $taxon): array
    {
        return $this->getTaxonImagesByTypes($taxon, ['bottom']);
    }

    public function getMediaUrl(?string $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->packages->getUrl('media/image/' . ltrim($path, '/'));
    }

    /**
     * Returns a media destination URL only when it is safe to render inside an "href" attribute.
     *
     * The value is sanitized again here, on read, so an unsafe URL (for instance a "javascript:"
     * one) can never reach the storefront.
     */
    public function getMediaHref(?string $url): ?string
    {
        return TaxonImage::sanitizeDestinationUrl($url);
    }

    public function getMediaMobileUrl(?string $path, string $filterSet = 'cyllene_universe_slider_mobile'): ?string
    {
        $desktopUrl = $this->getMediaUrl($path);
        if ($desktopUrl === null || $path === null) {
            return $desktopUrl;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $desktopUrl;
        }

        $filters = $this->filterConfiguration->all();
        if (!array_key_exists($filterSet, $filters)) {
            return $desktopUrl;
        }

        try {
            return $this->cacheManager->getBrowserPath(
                'media/image/' . ltrim($path, '/'),
                $filterSet,
                [],
                null,
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );
        } catch (\Throwable) {
            return $desktopUrl;
        }
    }

    /**
     * Returns the featured (spotlight) media URL of a taxon.
     *
     * The taxon display mode decides which media is picked: `stacked` keeps the media with the
     * lowest position, `random` picks one of the zone media at random on every call.
     */
    public function getFeaturedMediaUrl(TaxonInterface $taxon): ?string
    {
        $candidates = $this->getTaxonImagesByTypes($taxon, ['featured']);

        if ($candidates === []) {
            return null;
        }

        if ($taxon instanceof Taxon && $taxon->getMediaDisplayModeFeatured() === 'random') {
            return $this->getMediaUrl($candidates[(int) array_rand($candidates)]->getPath());
        }

        return $this->getMediaUrl($candidates[0]->getPath());
    }

    /**
     * @return array{mediaUrl: ?string, hasChildColor: bool, childChildren: array<int, TaxonInterface>}
     */
    public function getUniverseChildCardData(TaxonInterface $taxon): array
    {
        if (!$taxon instanceof Taxon) {
            return [
                'mediaUrl' => null,
                'hasChildColor' => false,
                'childChildren' => [],
            ];
        }

        $color = $taxon->getColor();

        return [
            'mediaUrl' => $this->getFeaturedMediaUrl($taxon),
            'hasChildColor' => is_string($color) && $color !== '',
            'childChildren' => array_values($taxon->getEnabledChildren()->toArray()),
        ];
    }

    /**
     * @param array<int, TaxonInterface> $children
     */
    public function hasFeaturedChildrenProducts(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof Taxon && !$child->getFeaturedProducts()->isEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $types
     *
     * @return array<int, TaxonImage>
     */
    private function getTaxonImagesByTypes(TaxonInterface $taxon, array $types): array
    {
        if (!$taxon instanceof Taxon) {
            return [];
        }

        $images = [];
        foreach ($taxon->getImages() as $image) {
            if (!$image instanceof TaxonImage) {
                continue;
            }

            $type = $image->getType();
            $path = $image->getPath();
            if (!is_string($type) || !in_array($type, $types, true)) {
                continue;
            }

            if (!is_string($path) || $path === '') {
                continue;
            }

            $images[] = $image;
        }

        usort($images, static fn (TaxonImage $left, TaxonImage $right): int => $left->getPosition() <=> $right->getPosition());

        return $images;
    }
}
