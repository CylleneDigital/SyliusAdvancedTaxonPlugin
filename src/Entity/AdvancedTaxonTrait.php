<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Advanced taxon fields, to be used by the application Taxon entity together with
 * {@see AdvancedTaxonInterface}. The application constructor must call initializeAdvancedTaxon().
 *
 * @phpstan-require-implements AdvancedTaxonInterface
 */
trait AdvancedTaxonTrait
{
    #[ORM\Column(name: 'color', type: 'string', length: 32, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(name: 'icon', type: 'string', length: 1024, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'icon_type', type: 'string', length: 255, nullable: true)]
    private ?string $iconType = null;

    #[ORM\Column(name: 'conditional', type: 'boolean', options: ['default' => false])]
    private bool $conditional = false;

    /** @var Collection<int, AdvancedTaxonInterface> */
    #[ORM\ManyToMany(targetEntity: TaxonInterface::class)]
    #[ORM\JoinTable(
        name: 'cyllene_advanced_taxon_featured_children',
        joinColumns: [new ORM\JoinColumn(name: 'taxon_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'featured_taxon_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $featuredChildren;

    /** @var Collection<int, ProductInterface> */
    #[ORM\ManyToMany(targetEntity: ProductInterface::class)]
    #[ORM\JoinTable(
        name: 'cyllene_advanced_taxon_featured_products',
        joinColumns: [new ORM\JoinColumn(name: 'taxon_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
    )]
    private Collection $featuredProducts;

    /** @var Collection<int, FacetCondition> */
    #[ORM\OneToMany(targetEntity: FacetCondition::class, mappedBy: 'taxon', cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $facetConditions;

    /** @var Collection<string, TaxonFeaturedItemsTranslation> */
    #[ORM\OneToMany(targetEntity: TaxonFeaturedItemsTranslation::class, mappedBy: 'taxon', cascade: ['all'], orphanRemoval: true, indexBy: 'locale')]
    #[ORM\OrderBy(['locale' => 'ASC'])]
    private Collection $featuredItemsTranslations;

    #[ORM\Column(name: 'is_slider', type: 'boolean', options: ['default' => false])]
    private bool $isSlider = false;

    #[ORM\Column(name: 'is_featured_products_active', type: 'boolean', options: ['default' => false])]
    private bool $isFeaturedProductsActive = false;

    #[ORM\Column(name: 'featured_products_position', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS])]
    private string $featuredProductsPosition = AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS;

    #[ORM\Column(name: 'media_display_mode_top', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED])]
    private string $mediaDisplayModeTop = AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED;

    #[ORM\Column(name: 'media_display_mode_bottom', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED])]
    private string $mediaDisplayModeBottom = AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED;

    #[ORM\Column(name: 'media_display_mode_left', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED])]
    private string $mediaDisplayModeLeft = AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED;

    #[ORM\Column(name: 'media_display_mode_product', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_END])]
    private string $mediaDisplayModeProduct = AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_END;

    #[ORM\Column(name: 'media_display_mode_featured', type: 'string', length: 32, options: ['default' => AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED])]
    private string $mediaDisplayModeFeatured = AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED;

    #[ORM\Column(name: 'include_children_products', type: 'boolean', options: ['default' => false])]
    private bool $includeChildrenProducts = false;

    #[ORM\Column(name: 'advanced_filters_enabled', type: 'boolean', options: ['default' => false])]
    private bool $advancedFiltersEnabled = false;

    #[ORM\Column(name: 'is_universe', type: 'boolean', options: ['default' => false])]
    private bool $isUniverse = false;

    #[ORM\Column(name: 'universe_background_use_taxon_color', type: 'boolean', options: ['default' => false])]
    private bool $universeBackgroundUseTaxonColor = false;

    #[ORM\Column(name: 'universe_title_background_enabled', type: 'boolean', options: ['default' => false])]
    private bool $universeTitleBackgroundEnabled = false;

    #[ORM\Column(name: 'show_customization_in_menu', type: 'boolean', options: ['default' => true])]
    private bool $showCustomizationInMenu = true;

    #[ORM\Column(name: 'show_customization_on_taxon_page', type: 'boolean', options: ['default' => true])]
    private bool $showCustomizationOnTaxonPage = true;

    #[ORM\Column(name: 'show_customization_in_breadcrumbs', type: 'boolean', options: ['default' => true])]
    private bool $showCustomizationInBreadcrumbs = true;

    protected function initializeAdvancedTaxon(): void
    {
        $this->featuredChildren = new ArrayCollection();
        $this->featuredProducts = new ArrayCollection();
        $this->facetConditions = new ArrayCollection();
        $this->featuredItemsTranslations = new ArrayCollection();
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = AdvancedTaxonValueSanitizer::sanitizeColor($color);
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = AdvancedTaxonValueSanitizer::sanitizeIcon($icon);
    }

    public function getIconType(): ?string
    {
        return $this->iconType;
    }

    public function setIconType(?string $iconType): void
    {
        $this->iconType = $iconType;
    }

    public function isConditional(): bool
    {
        return $this->conditional;
    }

    public function setConditional(bool $conditional): void
    {
        $this->conditional = $conditional;
    }

    /** @return Collection<int, AdvancedTaxonInterface> */
    public function getFeaturedChildren(): Collection
    {
        return $this->featuredChildren;
    }

    public function addFeaturedChild(AdvancedTaxonInterface $taxon): void
    {
        if (!$this->featuredChildren->contains($taxon)) {
            $this->featuredChildren->add($taxon);
        }
    }

    public function removeFeaturedChild(AdvancedTaxonInterface $taxon): void
    {
        if ($this->featuredChildren->contains($taxon)) {
            $this->featuredChildren->removeElement($taxon);
        }
    }

    /** @return Collection<int, ProductInterface> */
    public function getFeaturedProducts(): Collection
    {
        return $this->featuredProducts;
    }

    public function addFeaturedProduct(ProductInterface $product): void
    {
        if (!$this->featuredProducts->contains($product)) {
            $this->featuredProducts->add($product);
        }
    }

    public function removeFeaturedProduct(ProductInterface $product): void
    {
        if ($this->featuredProducts->contains($product)) {
            $this->featuredProducts->removeElement($product);
        }
    }

    public function isSlider(): bool
    {
        return $this->isSlider;
    }

    public function setIsSlider(bool $isSlider): void
    {
        $this->isSlider = $isSlider;
    }

    public function isFeaturedProductsActive(): bool
    {
        return $this->isFeaturedProductsActive;
    }

    public function setIsFeaturedProductsActive(bool $isFeaturedProductsActive): void
    {
        $this->isFeaturedProductsActive = $isFeaturedProductsActive;
    }

    public function getFeaturedProductsPosition(): string
    {
        return $this->featuredProductsPosition;
    }

    public function setFeaturedProductsPosition(string $featuredProductsPosition): void
    {
        if (!in_array($featuredProductsPosition, [AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS, AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS], true)) {
            $featuredProductsPosition = AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS;
        }

        $this->featuredProductsPosition = $featuredProductsPosition;
    }

    public function getMediaDisplayModeTop(): string
    {
        return $this->mediaDisplayModeTop;
    }

    public function setMediaDisplayModeTop(string $mediaDisplayModeTop): void
    {
        $this->mediaDisplayModeTop = self::mediaDisplayMode($mediaDisplayModeTop, true);
    }

    public function getMediaDisplayModeBottom(): string
    {
        return $this->mediaDisplayModeBottom;
    }

    public function setMediaDisplayModeBottom(string $mediaDisplayModeBottom): void
    {
        $this->mediaDisplayModeBottom = self::mediaDisplayMode($mediaDisplayModeBottom, true);
    }

    public function getMediaDisplayModeLeft(): string
    {
        return $this->mediaDisplayModeLeft;
    }

    public function setMediaDisplayModeLeft(string $mediaDisplayModeLeft): void
    {
        $this->mediaDisplayModeLeft = self::mediaDisplayMode($mediaDisplayModeLeft);
    }

    private static function mediaDisplayMode(string $mode, bool $sliderAllowed = false): string
    {
        $modes = [AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED, AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_RANDOM];
        if ($sliderAllowed) {
            $modes[] = AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_SLIDER;
        }

        return in_array($mode, $modes, true) ? $mode : AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_STACKED;
    }

    public function getMediaDisplayModeProduct(): string
    {
        return $this->mediaDisplayModeProduct;
    }

    public function setMediaDisplayModeProduct(string $mediaDisplayModeProduct): void
    {
        if (!in_array($mediaDisplayModeProduct, [
            AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_START,
            AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_END,
            AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE,
        ], true)) {
            $mediaDisplayModeProduct = AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_END;
        }

        $this->mediaDisplayModeProduct = $mediaDisplayModeProduct;
    }

    public function getMediaDisplayModeFeatured(): string
    {
        return $this->mediaDisplayModeFeatured;
    }

    public function setMediaDisplayModeFeatured(string $mediaDisplayModeFeatured): void
    {
        $this->mediaDisplayModeFeatured = self::mediaDisplayMode($mediaDisplayModeFeatured);
    }

    public function isIncludeChildrenProducts(): bool
    {
        return $this->includeChildrenProducts;
    }

    public function setIncludeChildrenProducts(bool $includeChildrenProducts): void
    {
        $this->includeChildrenProducts = $includeChildrenProducts;
    }

    public function isAdvancedFiltersEnabled(): bool
    {
        return $this->advancedFiltersEnabled;
    }

    public function setAdvancedFiltersEnabled(bool $advancedFiltersEnabled): void
    {
        $this->advancedFiltersEnabled = $advancedFiltersEnabled;
    }

    public function isUniverse(): bool
    {
        return $this->isUniverse;
    }

    public function setIsUniverse(bool $isUniverse): void
    {
        $this->isUniverse = $isUniverse;
    }

    public function isUniverseBackgroundUseTaxonColor(): bool
    {
        return $this->universeBackgroundUseTaxonColor;
    }

    public function setUniverseBackgroundUseTaxonColor(bool $universeBackgroundUseTaxonColor): void
    {
        $this->universeBackgroundUseTaxonColor = $universeBackgroundUseTaxonColor;
    }

    public function isUniverseTitleBackgroundEnabled(): bool
    {
        return $this->universeTitleBackgroundEnabled;
    }

    public function setUniverseTitleBackgroundEnabled(bool $universeTitleBackgroundEnabled): void
    {
        $this->universeTitleBackgroundEnabled = $universeTitleBackgroundEnabled;
    }

    public function isShowCustomizationInMenu(): bool
    {
        return $this->showCustomizationInMenu;
    }

    public function setShowCustomizationInMenu(bool $showCustomizationInMenu): void
    {
        $this->showCustomizationInMenu = $showCustomizationInMenu;
    }

    public function isShowCustomizationOnTaxonPage(): bool
    {
        return $this->showCustomizationOnTaxonPage;
    }

    public function setShowCustomizationOnTaxonPage(bool $showCustomizationOnTaxonPage): void
    {
        $this->showCustomizationOnTaxonPage = $showCustomizationOnTaxonPage;
    }

    public function isShowCustomizationInBreadcrumbs(): bool
    {
        return $this->showCustomizationInBreadcrumbs;
    }

    public function setShowCustomizationInBreadcrumbs(bool $showCustomizationInBreadcrumbs): void
    {
        $this->showCustomizationInBreadcrumbs = $showCustomizationInBreadcrumbs;
    }

    /** @return Collection<int, FacetCondition> */
    public function getFacetConditions(): Collection
    {
        return $this->facetConditions;
    }

    public function addFacetCondition(FacetCondition $condition): void
    {
        if (!$this->facetConditions->contains($condition)) {
            $this->facetConditions->add($condition);
            $condition->setTaxon($this);
        }

        // A taxon with conditions is conditional, whatever wrote them (form, API, fixtures, import).
        $this->conditional = true;
    }

    public function removeFacetCondition(FacetCondition $condition): void
    {
        if ($this->facetConditions->removeElement($condition) && $condition->getTaxon() === $this) {
            $condition->setTaxon(null);
        }

        if ($this->facetConditions->isEmpty()) {
            $this->conditional = false;
        }
    }

    /**
     * @return Collection<string, TaxonFeaturedItemsTranslation>
     */
    public function getFeaturedItemsTranslations(): Collection
    {
        return $this->featuredItemsTranslations;
    }

    public function addFeaturedItemsTranslation(TaxonFeaturedItemsTranslation $translation): void
    {
        $locale = $translation->getLocale();
        if ($locale === null) {
            return;
        }

        if ($this->featuredItemsTranslations->get($locale) !== $translation) {
            $this->featuredItemsTranslations->set($locale, $translation);
        }

        if ($translation->getTaxon() !== $this) {
            $translation->setTaxon($this);
        }
    }

    public function removeFeaturedItemsTranslation(TaxonFeaturedItemsTranslation $translation): void
    {
        $locale = $translation->getLocale();
        if ($locale === null) {
            return;
        }

        $removed = $this->featuredItemsTranslations->remove($locale);
        if ($removed !== null && $translation->getTaxon() === $this) {
            $translation->setTaxon(null);
        }
    }

    public function getFeaturedItemsTranslation(?string $locale = null): ?TaxonFeaturedItemsTranslation
    {
        foreach ([$locale, $this->currentLocale, $this->fallbackLocale] as $candidateLocale) {
            $translation = is_string($candidateLocale) ? $this->featuredItemsTranslations->get($candidateLocale) : null;
            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    public function getOrCreateFeaturedItemsTranslation(string $locale): TaxonFeaturedItemsTranslation
    {
        // The exact locale only: the fallback chain of getFeaturedItemsTranslation() would hand back
        // another locale's texts, which the caller would then overwrite.
        $translation = $this->featuredItemsTranslations->get($locale);
        if (!$translation instanceof TaxonFeaturedItemsTranslation) {
            $translation = new TaxonFeaturedItemsTranslation();
            $translation->setLocale($locale);
            $translation->setTaxon($this);
            $this->addFeaturedItemsTranslation($translation);
        }

        return $translation;
    }

    public function getFeaturedProductsTitle(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getFeaturedProductsTitle();
    }

    public function getFeaturedProductsDescription(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getFeaturedProductsDescription();
    }

    public function getFeaturedChildrenTitle(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getFeaturedChildrenTitle();
    }

    public function getFeaturedChildrenDescription(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getFeaturedChildrenDescription();
    }

    public function getUniversePageTitle(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getUniversePageTitle();
    }

    public function getUniversePageDescription(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getUniversePageDescription();
    }

    public function getUniverseFeaturedProductsTitle(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getUniverseFeaturedProductsTitle();
    }

    public function getUniverseFeaturedProductsDescription(?string $locale = null): ?string
    {
        return $this->getFeaturedItemsTranslation($locale)?->getUniverseFeaturedProductsDescription();
    }
}
