<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\TaxonInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cyllene_advanced_taxon_featured_items_translation')]
#[ORM\UniqueConstraint(name: 'featured_items_translation_uniq', columns: ['taxon_id', 'locale'])]
/**
 * Featured items and universe texts of a taxon in one locale. Not a Sylius translation: the taxon
 * keeps its own, this one is attached to it by `taxon` and `locale`.
 */
class TaxonFeaturedItemsTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $locale = null;

    #[ORM\ManyToOne(targetEntity: TaxonInterface::class, inversedBy: 'featuredItemsTranslations')]
    #[ORM\JoinColumn(name: 'taxon_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AdvancedTaxonInterface $taxon = null;

    #[ORM\Column(name: 'featured_products_title', type: 'string', length: 255, nullable: true)]
    private ?string $featuredProductsTitle = null;

    #[ORM\Column(name: 'featured_products_description', type: 'text', nullable: true)]
    private ?string $featuredProductsDescription = null;

    #[ORM\Column(name: 'featured_children_title', type: 'string', length: 255, nullable: true)]
    private ?string $featuredChildrenTitle = null;

    #[ORM\Column(name: 'featured_children_description', type: 'text', nullable: true)]
    private ?string $featuredChildrenDescription = null;

    #[ORM\Column(name: 'universe_page_title', type: 'string', length: 255, nullable: true)]
    private ?string $universePageTitle = null;

    #[ORM\Column(name: 'universe_page_description', type: 'text', nullable: true)]
    private ?string $universePageDescription = null;

    #[ORM\Column(name: 'universe_featured_products_title', type: 'string', length: 255, nullable: true)]
    private ?string $universeFeaturedProductsTitle = null;

    #[ORM\Column(name: 'universe_featured_products_description', type: 'text', nullable: true)]
    private ?string $universeFeaturedProductsDescription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxon(): ?AdvancedTaxonInterface
    {
        return $this->taxon;
    }

    public function setTaxon(?AdvancedTaxonInterface $taxon): void
    {
        $this->taxon = $taxon;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getFeaturedProductsTitle(): ?string
    {
        return $this->featuredProductsTitle;
    }

    public function setFeaturedProductsTitle(?string $featuredProductsTitle): void
    {
        $this->featuredProductsTitle = $featuredProductsTitle;
    }

    public function getFeaturedProductsDescription(): ?string
    {
        return $this->featuredProductsDescription;
    }

    public function setFeaturedProductsDescription(?string $featuredProductsDescription): void
    {
        $this->featuredProductsDescription = $featuredProductsDescription;
    }

    public function getFeaturedChildrenTitle(): ?string
    {
        return $this->featuredChildrenTitle;
    }

    public function setFeaturedChildrenTitle(?string $featuredChildrenTitle): void
    {
        $this->featuredChildrenTitle = $featuredChildrenTitle;
    }

    public function getFeaturedChildrenDescription(): ?string
    {
        return $this->featuredChildrenDescription;
    }

    public function setFeaturedChildrenDescription(?string $featuredChildrenDescription): void
    {
        $this->featuredChildrenDescription = $featuredChildrenDescription;
    }

    public function getUniversePageTitle(): ?string
    {
        return $this->universePageTitle;
    }

    public function setUniversePageTitle(?string $universePageTitle): void
    {
        $this->universePageTitle = $universePageTitle;
    }

    public function getUniversePageDescription(): ?string
    {
        return $this->universePageDescription;
    }

    public function setUniversePageDescription(?string $universePageDescription): void
    {
        $this->universePageDescription = $universePageDescription;
    }

    public function getUniverseFeaturedProductsTitle(): ?string
    {
        return $this->universeFeaturedProductsTitle;
    }

    public function setUniverseFeaturedProductsTitle(?string $universeFeaturedProductsTitle): void
    {
        $this->universeFeaturedProductsTitle = $universeFeaturedProductsTitle;
    }

    public function getUniverseFeaturedProductsDescription(): ?string
    {
        return $this->universeFeaturedProductsDescription;
    }

    public function setUniverseFeaturedProductsDescription(?string $universeFeaturedProductsDescription): void
    {
        $this->universeFeaturedProductsDescription = $universeFeaturedProductsDescription;
    }
}
