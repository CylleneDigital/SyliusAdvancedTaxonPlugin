<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonValueSanitizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class UniverseRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly VisibilityRuntime $visibility,
        private readonly CacheManager $cacheManager,
        private readonly FilterConfiguration $filterConfiguration,
    ) {
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
        $color = $taxon instanceof AdvancedTaxonInterface ? $taxon->getColor() : null;
        $hasColor = is_string($color) && $color !== '';

        $universePageTitle = $taxon instanceof AdvancedTaxonInterface ? $taxon->getUniversePageTitle() : null;
        $universePageDescription = $taxon instanceof AdvancedTaxonInterface ? $taxon->getUniversePageDescription() : null;
        $universeFeaturedTitle = $taxon instanceof AdvancedTaxonInterface ? $taxon->getUniverseFeaturedProductsTitle() : null;
        $universeFeaturedDescription = $taxon instanceof AdvancedTaxonInterface ? $taxon->getUniverseFeaturedProductsDescription() : null;

        return [
            'useTaxonColorBackground' => $hasColor && $taxon instanceof AdvancedTaxonInterface && $taxon->isUniverseBackgroundUseTaxonColor(),
            'titleBackgroundEnabled' => $hasColor && $taxon instanceof AdvancedTaxonInterface && $taxon->isUniverseTitleBackgroundEnabled(),
            'universeButtonColor' => $hasColor ? $color : '#0d6efd',
            'universeAccentColor' => $hasColor ? $color : '#ffffff',
            'universePageTitle' => (is_string($universePageTitle) && $universePageTitle !== '') ? $universePageTitle : (string) $taxon->getName(),
            'universePageDescription' => is_string($universePageDescription) ? $universePageDescription : null,
            'universeFeaturedTitle' => is_string($universeFeaturedTitle) ? $universeFeaturedTitle : null,
            'universeFeaturedDescription' => is_string($universeFeaturedDescription) ? $universeFeaturedDescription : null,
        ];
    }

    /**
     * @return array<int, AdvancedTaxonImageInterface>
     */
    public function getUniverseSliderMedia(TaxonInterface $taxon): array
    {
        return $this->getTaxonImagesByTypes($taxon, ['slider_universe']);
    }

    /**
     * @return array<int, AdvancedTaxonImageInterface>
     */
    public function getUniverseBottomMedia(TaxonInterface $taxon): array
    {
        return $this->getTaxonImagesByTypes($taxon, ['bottom']);
    }

    /**
     * URL of a taxon media, read through Liip Imagine from the Sylius image storage (local, S3, ...).
     */
    public function getMediaUrl(?string $path, string $filterSet = 'sylius_original'): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $this->cacheManager->getBrowserPath(ltrim($path, '/'), $filterSet, [], null, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Returns a media destination URL only when it is safe to render inside an "href" attribute.
     *
     * The value is sanitized again here, on read, so an unsafe URL (for instance a "javascript:"
     * one) can never reach the storefront.
     */
    public function getMediaHref(?string $url): ?string
    {
        return AdvancedTaxonValueSanitizer::sanitizeDestinationUrl($url);
    }

    public function getMediaMobileUrl(?string $path, string $filterSet = 'cyllene_universe_slider_mobile'): ?string
    {
        return $this->getMediaUrl($path, array_key_exists($filterSet, $this->filterConfiguration->all()) ? $filterSet : 'sylius_original');
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

        if ($taxon instanceof AdvancedTaxonInterface && $taxon->getMediaDisplayModeFeatured() === AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_RANDOM) {
            return $this->getMediaUrl($candidates[(int) array_rand($candidates)]->getPath());
        }

        return $this->getMediaUrl($candidates[0]->getPath());
    }

    /**
     * @return array{mediaUrl: ?string, hasChildColor: bool, childChildren: array<int, TaxonInterface>}
     */
    public function getUniverseChildCardData(TaxonInterface $taxon): array
    {
        if (!$taxon instanceof AdvancedTaxonInterface) {
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
            if (
                $child instanceof AdvancedTaxonInterface &&
                $child->isFeaturedProductsActive() &&
                $this->visibility->visibleProducts($child->getFeaturedProducts()) !== []
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $types
     *
     * @return array<int, AdvancedTaxonImageInterface>
     */
    private function getTaxonImagesByTypes(TaxonInterface $taxon, array $types): array
    {
        if (!$taxon instanceof AdvancedTaxonInterface) {
            return [];
        }

        $images = [];
        foreach ($taxon->getImages() as $image) {
            if (!$image instanceof AdvancedTaxonImageInterface) {
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

        usort($images, static fn (AdvancedTaxonImageInterface $left, AdvancedTaxonImageInterface $right): int => $left->getPosition() <=> $right->getPosition());

        return $images;
    }
}
