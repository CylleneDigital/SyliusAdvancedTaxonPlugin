<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
class Taxon extends BaseTaxon
{
    public const FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS = 'before_filters';

    public const FEATURED_PRODUCTS_POSITION_AFTER_FILTERS = 'after_filters';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_START = 'products_start';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_END = 'products_end';

    public const MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE = 'products_random_middle';

    /**
     * Web path prefix under which pictograms uploaded through the admin are stored.
     *
     * Shared with the uploader so that input sanitization and storage agree on a single value.
     */
    public const ICON_PATH_PREFIX = '/media/advanced-taxon/icons';

    #[ORM\Column(name: 'color', type: 'string', length: 32, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(name: 'icon', type: 'string', length: 1024, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'icon_type', type: 'string', length: 255, nullable: true)]
    private ?string $iconType = null;

    #[ORM\Column(name: 'conditional', type: 'boolean', options: ['default' => false])]
    private bool $conditional = false;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'facet_configuration', type: 'json', nullable: true)]
    private ?array $facetConfiguration = null;

    /** @var Collection<int, self> */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'featuredBy')]
    #[ORM\JoinTable(
        name: 'cyllene_advanced_taxon_featured_children',
        joinColumns: [new ORM\JoinColumn(name: 'taxon_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'featured_taxon_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
    )]
    private Collection $featuredChildren;

    /** @var Collection<int, self> */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'featuredChildren')]
    private Collection $featuredBy;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'cyllene_advanced_taxon_featured_products')]
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

    #[ORM\Column(name: 'featured_products_position', type: 'string', length: 32, options: ['default' => self::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS])]
    private string $featuredProductsPosition = self::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS;

    #[ORM\Column(name: 'media_display_mode_top', type: 'string', length: 32, options: ['default' => 'stacked'])]
    private string $mediaDisplayModeTop = 'stacked';

    #[ORM\Column(name: 'media_display_mode_bottom', type: 'string', length: 32, options: ['default' => 'stacked'])]
    private string $mediaDisplayModeBottom = 'stacked';

    #[ORM\Column(name: 'media_display_mode_left', type: 'string', length: 32, options: ['default' => 'stacked'])]
    private string $mediaDisplayModeLeft = 'stacked';

    #[ORM\Column(name: 'media_display_mode_product', type: 'string', length: 32, options: ['default' => self::MEDIA_RIGHT_PRODUCTS_POSITION_END])]
    private string $mediaDisplayModeProduct = self::MEDIA_RIGHT_PRODUCTS_POSITION_END;

    #[ORM\Column(name: 'media_display_mode_featured', type: 'string', length: 32, options: ['default' => 'stacked'])]
    private string $mediaDisplayModeFeatured = 'stacked';

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

    public function __construct()
    {
        parent::__construct();

        $this->featuredChildren = new ArrayCollection();
        $this->featuredBy = new ArrayCollection();
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
        $this->color = $color;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = self::sanitizeIcon($icon);
    }

    /**
     * A taxon icon is either a pictogram uploaded by the plugin, stored under
     * self::ICON_PATH_PREFIX in the application public directory, or a glyph name from one of the
     * configured icon libraries.
     *
     * Everything else (another local path, a remote URL, a protocol relative path,
     * "javascript:", ...) is rejected so it can never be rendered as an image source or used for
     * an icon lookup.
     */
    public static function sanitizeIcon(?string $icon): ?string
    {
        if ($icon === null) {
            return null;
        }

        $icon = trim($icon);

        if ($icon === '') {
            return null;
        }

        if (str_starts_with($icon, self::ICON_PATH_PREFIX . '/')) {
            return $icon;
        }

        return preg_match('#^[a-z0-9][a-z0-9:_-]*$#i', $icon) === 1 ? $icon : null;
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

    /**
     * @return array<string, mixed>|null
     */
    public function getFacetConfiguration(): ?array
    {
        return $this->facetConfiguration;
    }

    /**
     * @param array<string, mixed>|null $facetConfiguration
     */
    public function setFacetConfiguration(?array $facetConfiguration): void
    {
        $this->facetConfiguration = $facetConfiguration;
    }

    /** @return Collection<int, self> */
    public function getFeaturedChildren(): Collection
    {
        return $this->featuredChildren;
    }

    public function addFeaturedChild(self $taxon): void
    {
        if (!$this->featuredChildren->contains($taxon)) {
            $this->featuredChildren->add($taxon);
            $taxon->addFeaturedBy($this);
        }
    }

    public function removeFeaturedChild(self $taxon): void
    {
        if ($this->featuredChildren->contains($taxon)) {
            $this->featuredChildren->removeElement($taxon);
            $taxon->removeFeaturedBy($this);
        }
    }

    /** @return Collection<int, self> */
    public function getFeaturedBy(): Collection
    {
        return $this->featuredBy;
    }

    public function addFeaturedBy(self $taxon): void
    {
        if (!$this->featuredBy->contains($taxon)) {
            $this->featuredBy->add($taxon);
        }
    }

    public function removeFeaturedBy(self $taxon): void
    {
        if ($this->featuredBy->contains($taxon)) {
            $this->featuredBy->removeElement($taxon);
        }
    }

    /** @return Collection<int, Product> */
    public function getFeaturedProducts(): Collection
    {
        return $this->featuredProducts;
    }

    public function addFeaturedProduct(Product $product): void
    {
        if (!$this->featuredProducts->contains($product)) {
            $this->featuredProducts->add($product);
        }
    }

    public function removeFeaturedProduct(Product $product): void
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
        if (!in_array($featuredProductsPosition, [self::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS, self::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS], true)) {
            $featuredProductsPosition = self::FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS;
        }

        $this->featuredProductsPosition = $featuredProductsPosition;
    }

    public function getMediaDisplayModeTop(): string
    {
        return $this->mediaDisplayModeTop;
    }

    public function setMediaDisplayModeTop(string $mediaDisplayModeTop): void
    {
        $this->mediaDisplayModeTop = $mediaDisplayModeTop;
    }

    public function getMediaDisplayModeBottom(): string
    {
        return $this->mediaDisplayModeBottom;
    }

    public function setMediaDisplayModeBottom(string $mediaDisplayModeBottom): void
    {
        $this->mediaDisplayModeBottom = $mediaDisplayModeBottom;
    }

    public function getMediaDisplayModeLeft(): string
    {
        return $this->mediaDisplayModeLeft;
    }

    public function setMediaDisplayModeLeft(string $mediaDisplayModeLeft): void
    {
        $this->mediaDisplayModeLeft = $mediaDisplayModeLeft;
    }

    public function getMediaDisplayModeProduct(): string
    {
        return $this->mediaDisplayModeProduct;
    }

    public function setMediaDisplayModeProduct(string $mediaDisplayModeProduct): void
    {
        if (!in_array($mediaDisplayModeProduct, [
            self::MEDIA_RIGHT_PRODUCTS_POSITION_START,
            self::MEDIA_RIGHT_PRODUCTS_POSITION_END,
            self::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE,
        ], true)) {
            $mediaDisplayModeProduct = self::MEDIA_RIGHT_PRODUCTS_POSITION_END;
        }

        $this->mediaDisplayModeProduct = $mediaDisplayModeProduct;
    }

    public function getMediaDisplayModeFeatured(): string
    {
        return $this->mediaDisplayModeFeatured;
    }

    public function setMediaDisplayModeFeatured(string $mediaDisplayModeFeatured): void
    {
        $this->mediaDisplayModeFeatured = $mediaDisplayModeFeatured;
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
    }

    public function removeFacetCondition(FacetCondition $condition): void
    {
        if ($this->facetConditions->removeElement($condition)) {
            if ($condition->getTaxon() === $this) {
                $condition->setTaxon(null);
            }
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

        if (!$this->featuredItemsTranslations->containsKey($locale)) {
            $this->featuredItemsTranslations->set($locale, $translation);
        } elseif ($this->featuredItemsTranslations->get($locale) !== $translation) {
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
        if ($locale !== null) {
            $translation = $this->featuredItemsTranslations->get($locale);
            if ($translation instanceof TaxonFeaturedItemsTranslation) {
                return $translation;
            }
        }

        if (is_string($this->currentLocale)) {
            $translation = $this->featuredItemsTranslations->get($this->currentLocale);
            if ($translation instanceof TaxonFeaturedItemsTranslation) {
                return $translation;
            }
        }

        if (is_string($this->fallbackLocale)) {
            $translation = $this->featuredItemsTranslations->get($this->fallbackLocale);
            if ($translation instanceof TaxonFeaturedItemsTranslation) {
                return $translation;
            }
        }

        return null;
    }

    public function getOrCreateFeaturedItemsTranslation(string $locale): TaxonFeaturedItemsTranslation
    {
        $translation = $this->getFeaturedItemsTranslation($locale);
        if ($translation === null) {
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
