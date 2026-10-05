<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

interface AdvancedTaxonInterface extends TaxonInterface
{
    public const FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS = 'before_filters';

    public const FEATURED_PRODUCTS_POSITION_AFTER_FILTERS = 'after_filters';

    public const MEDIA_DISPLAY_MODE_STACKED = 'stacked';

    public const MEDIA_DISPLAY_MODE_RANDOM = 'random';

    /**
     * One media at a time, changing on its own: top and bottom zones only.
     */
    public const MEDIA_DISPLAY_MODE_SLIDER = 'slider';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_START = 'products_start';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_END = 'products_end';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE = 'products_random_middle';

    /**
     * Prefix of the icon value of a pictogram uploaded through the admin, followed by the path of
     * the file in the Sylius image storage.
     */
    public const ICON_IMAGE_PREFIX = 'image:';

    public function getColor(): ?string;

    public function setColor(?string $color): void;

    public function getIcon(): ?string;

    public function setIcon(?string $icon): void;

    public function getIconType(): ?string;

    public function setIconType(?string $iconType): void;

    public function isConditional(): bool;

    public function setConditional(bool $conditional): void;

    /**
     * @return Collection<int, AdvancedTaxonInterface>
     */
    public function getFeaturedChildren(): Collection;

    public function addFeaturedChild(self $taxon): void;

    public function removeFeaturedChild(self $taxon): void;

    /**
     * @return Collection<int, ProductInterface>
     */
    public function getFeaturedProducts(): Collection;

    public function addFeaturedProduct(ProductInterface $product): void;

    public function removeFeaturedProduct(ProductInterface $product): void;

    public function isSlider(): bool;

    public function setIsSlider(bool $isSlider): void;

    public function isFeaturedProductsActive(): bool;

    public function setIsFeaturedProductsActive(bool $isFeaturedProductsActive): void;

    public function getFeaturedProductsPosition(): string;

    public function setFeaturedProductsPosition(string $featuredProductsPosition): void;

    public function getMediaDisplayModeTop(): string;

    public function setMediaDisplayModeTop(string $mediaDisplayModeTop): void;

    public function getMediaDisplayModeBottom(): string;

    public function setMediaDisplayModeBottom(string $mediaDisplayModeBottom): void;

    public function getMediaDisplayModeLeft(): string;

    public function setMediaDisplayModeLeft(string $mediaDisplayModeLeft): void;

    public function getMediaDisplayModeProduct(): string;

    public function setMediaDisplayModeProduct(string $mediaDisplayModeProduct): void;

    public function getMediaDisplayModeFeatured(): string;

    public function setMediaDisplayModeFeatured(string $mediaDisplayModeFeatured): void;

    public function isIncludeChildrenProducts(): bool;

    public function setIncludeChildrenProducts(bool $includeChildrenProducts): void;

    public function isAdvancedFiltersEnabled(): bool;

    public function setAdvancedFiltersEnabled(bool $advancedFiltersEnabled): void;

    public function isUniverse(): bool;

    public function setIsUniverse(bool $isUniverse): void;

    public function isUniverseBackgroundUseTaxonColor(): bool;

    public function setUniverseBackgroundUseTaxonColor(bool $universeBackgroundUseTaxonColor): void;

    public function isUniverseTitleBackgroundEnabled(): bool;

    public function setUniverseTitleBackgroundEnabled(bool $universeTitleBackgroundEnabled): void;

    public function isShowCustomizationInMenu(): bool;

    public function setShowCustomizationInMenu(bool $showCustomizationInMenu): void;

    public function isShowCustomizationOnTaxonPage(): bool;

    public function setShowCustomizationOnTaxonPage(bool $showCustomizationOnTaxonPage): void;

    public function isShowCustomizationInBreadcrumbs(): bool;

    public function setShowCustomizationInBreadcrumbs(bool $showCustomizationInBreadcrumbs): void;

    /**
     * @return Collection<int, FacetCondition>
     */
    public function getFacetConditions(): Collection;

    public function addFacetCondition(FacetCondition $condition): void;

    public function removeFacetCondition(FacetCondition $condition): void;

    /**
     * @return Collection<string, TaxonFeaturedItemsTranslation>
     */
    public function getFeaturedItemsTranslations(): Collection;

    public function addFeaturedItemsTranslation(TaxonFeaturedItemsTranslation $translation): void;

    public function removeFeaturedItemsTranslation(TaxonFeaturedItemsTranslation $translation): void;

    public function getFeaturedItemsTranslation(?string $locale = null): ?TaxonFeaturedItemsTranslation;

    public function getOrCreateFeaturedItemsTranslation(string $locale): TaxonFeaturedItemsTranslation;

    public function getFeaturedProductsTitle(?string $locale = null): ?string;

    public function getFeaturedProductsDescription(?string $locale = null): ?string;

    public function getFeaturedChildrenTitle(?string $locale = null): ?string;

    public function getFeaturedChildrenDescription(?string $locale = null): ?string;

    public function getUniversePageTitle(?string $locale = null): ?string;

    public function getUniversePageDescription(?string $locale = null): ?string;

    public function getUniverseFeaturedProductsTitle(?string $locale = null): ?string;

    public function getUniverseFeaturedProductsDescription(?string $locale = null): ?string;
}
