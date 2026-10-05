<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Grid;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

/**
 * Query of the Sylius shop product grid on a taxon page, extended with the per-taxon options of the
 * plugin. The grid keeps its pagination, sorting, search filter and channel restriction.
 */
final readonly class ShopProductListQueryBuilder
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private FacetProductQueryBuilder $facetProductQueryBuilder,
    ) {
    }

    /**
     * @param array<string, string> $sorting
     * @param array<string, mixed> $advancedFilters
     */
    public function createListQueryBuilder(
        ChannelInterface $channel,
        TaxonInterface $taxon,
        string $locale,
        array $sorting = [],
        bool $includeAllDescendants = false,
        array $advancedFilters = [],
    ): QueryBuilder {
        $isAdvancedTaxon = $taxon instanceof AdvancedTaxonInterface;

        $queryBuilder = $this->productRepository->createShopListQueryBuilder(
            $channel,
            $taxon,
            $locale,
            $sorting,
            $includeAllDescendants || ($isAdvancedTaxon && $taxon->isIncludeChildrenProducts()),
        );

        if ($isAdvancedTaxon && $taxon->isAdvancedFiltersEnabled() && $advancedFilters !== []) {
            $this->facetProductQueryBuilder->applyStorefrontFilters($queryBuilder, $advancedFilters, $locale);
        }

        return $queryBuilder;
    }
}
