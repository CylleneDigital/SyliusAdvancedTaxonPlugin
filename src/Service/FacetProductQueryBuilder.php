<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Storefront side of the advanced filters: the filters applied to the shop product grid, and the
 * facets (values and counts) offered on a taxon page, counted on exactly what the grid lists.
 *
 * @phpstan-type NormalizedFilters array{
 *   attributes: array<string, array<int, string>>,
 *   options: array<string, array<int, string>>,
 *   taxons: array<int, int>,
 *   price: array{min: int|null, max: int|null},
 *   search: string
 * }
 */
final readonly class FacetProductQueryBuilder
{
    /**
     * Attribute and option codes a storefront request may filter on, all together.
     */
    public const int MAX_FILTER_CODES = 10;

    /**
     * Values kept per filtered code, and sub-taxons kept.
     */
    public const int MAX_FILTER_VALUES = 50;

    /**
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private EntityManagerInterface $em,
        private string $productClass,
        private ChannelContextInterface $channelContext,
        private SelectAttributeValueResolver $selectAttributeValues,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   selected: array<string, mixed>,
     *   facets: array{
     *     attributes: array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>,
     *     options: array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>,
     *     taxons: array<int, array{id: int, code: string, label: string, count: int, active: bool}>,
     *     price: array{min: float|null, max: float|null, selectedMin: float|null, selectedMax: float|null}
     *   }
     * }
     */
    public function getAdvancedFiltersData(
        AdvancedTaxonInterface $taxon,
        string $localeCode,
        bool $includeChildren = false,
        array $filters = [],
    ): array {
        $normalized = $this->normalizeAdvancedFilters($filters);

        $attributeFacets = $this->getAttributeFacets($taxon, $localeCode, $includeChildren, $normalized);
        $optionFacets = $this->getOptionFacets($taxon, $localeCode, $includeChildren, $normalized);
        $taxonFacets = $this->getTaxonFacets($taxon, $localeCode, $includeChildren, $normalized);
        $priceRange = $this->getPriceFacet($taxon, $localeCode, $includeChildren, $normalized);

        return [
            'selected' => $normalized,
            'facets' => [
                'attributes' => $attributeFacets,
                'options' => $optionFacets,
                'taxons' => $taxonFacets,
                'price' => $priceRange,
            ],
        ];
    }

    /**
     * Applies the storefront advanced filters (the "advanced" query parameter) to a product list
     * query, such as the one of the Sylius shop product grid.
     *
     * @param array<string, mixed> $filters
     */
    public function applyStorefrontFilters(QueryBuilder $qb, array $filters, string $localeCode): void
    {
        // The grid applies its own search filter.
        $normalized = $this->normalizeAdvancedFilters($filters);
        $normalized['search'] = '';

        $this->applyAdvancedFilters($qb, $normalized, $localeCode, [], null, $qb->getRootAliases()[0]);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return NormalizedFilters
     */
    private function normalizeAdvancedFilters(array $filters): array
    {
        // Every selected code adds joins and facet queries: an unbounded query string would let anyone
        // build a query MySQL refuses (61 tables at most) or the database chokes on.
        $codesLeft = self::MAX_FILTER_CODES;
        $normalizedAttributes = self::normalizeValuesByCode($filters['attributes'] ?? [], $codesLeft);
        $normalizedOptions = self::normalizeValuesByCode($filters['options'] ?? [], $codesLeft);

        $normalizedTaxons = [];
        $taxons = $filters['taxons'] ?? [];
        if (is_array($taxons)) {
            foreach ($taxons as $taxonId) {
                if (!is_scalar($taxonId)) {
                    continue;
                }

                $id = (int) $taxonId;
                if ($id > 0 && count($normalizedTaxons) < self::MAX_FILTER_VALUES) {
                    $normalizedTaxons[$id] = $id;
                }
            }
        }

        $min = null;
        $max = null;
        $price = $filters['price'] ?? [];
        if (is_array($price)) {
            if (isset($price['min']) && is_scalar($price['min']) && $price['min'] !== '') {
                $min = max(0, (int) round(((float) $price['min']) * 100));
            }

            if (isset($price['max']) && is_scalar($price['max']) && $price['max'] !== '') {
                $max = max(0, (int) round(((float) $price['max']) * 100));
            }
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'attributes' => $normalizedAttributes,
            'options' => $normalizedOptions,
            'taxons' => array_values($normalizedTaxons),
            'price' => [
                'min' => $min,
                'max' => $max,
            ],
            'search' => is_string($filters['search'] ?? null) ? trim($filters['search']) : '',
        ];
    }

    /**
     * @return array<string, list<string>> trimmed, deduplicated, non-empty values by code
     */
    private static function normalizeValuesByCode(mixed $valuesByCode, int &$codesLeft): array
    {
        if (!is_array($valuesByCode)) {
            return [];
        }

        $normalized = [];
        foreach ($valuesByCode as $code => $values) {
            if ($codesLeft <= 0) {
                break;
            }

            if (!is_string($code) || $code === '' || !is_array($values)) {
                continue;
            }

            $normalizedValues = [];
            foreach ($values as $value) {
                $trimmed = is_string($value) ? trim($value) : '';
                if ($trimmed !== '' && count($normalizedValues) < self::MAX_FILTER_VALUES) {
                    $normalizedValues[$trimmed] = $trimmed;
                }
            }

            if ($normalizedValues !== []) {
                $normalized[$code] = array_values($normalizedValues);
                --$codesLeft;
            }
        }

        return $normalized;
    }

    /**
     * @param NormalizedFilters $filters
     * @param array{
     *   excludeAttributeCodes?: array<int, string>,
     *   excludeOptionCodes?: array<int, string>,
     *   excludeTaxons?: bool,
     *   excludePrice?: bool
     * } $exclusions
     * @param string|null $variantAlias enabled variant alias already joined by the caller, on which
     *                                  the option and price filters apply: they always target one
     *                                  and the same variant
     */
    private function applyAdvancedFilters(
        QueryBuilder $qb,
        array $filters,
        string $localeCode,
        array $exclusions = [],
        ?string $variantAlias = null,
        string $rootAlias = 'p',
    ): void {
        $excludedAttributeCodes = array_flip($exclusions['excludeAttributeCodes'] ?? []);
        $excludedOptionCodes = array_flip($exclusions['excludeOptionCodes'] ?? []);
        $excludeTaxons = (bool) ($exclusions['excludeTaxons'] ?? false);
        $excludePrice = (bool) ($exclusions['excludePrice'] ?? false);

        $attributeIndex = 0;
        foreach ($filters['attributes'] as $attributeCode => $values) {
            if ($values === [] || isset($excludedAttributeCodes[$attributeCode])) {
                continue;
            }

            $attributeValueAlias = 'adv_pav_' . $attributeIndex;
            $attributeAlias = 'adv_pa_' . $attributeIndex;
            $codeParam = 'adv_attr_code_' . $attributeIndex;
            $valuesParam = 'adv_attr_values_' . $attributeIndex;
            $localeParam = 'adv_attr_locale_' . $attributeIndex;

            $qb->setParameter($codeParam, $attributeCode);

            $textMatch = sprintf(
                'TRIM(%1$s.text) IN (:%2$s) AND (%1$s.localeCode = :%3$s OR %1$s.localeCode IS NULL)',
                $attributeValueAlias,
                $valuesParam,
                $localeParam,
            );
            $qb->setParameter($valuesParam, $values)->setParameter($localeParam, $localeCode);

            $selectValueIds = $this->selectAttributeValues->findMatchingValueIds(
                $attributeCode,
                static fn (array $candidates): bool => in_array($candidates[0], $values, true),
            );

            $valueMatch = $selectValueIds === []
                ? $textMatch
                : (string) $qb->expr()->orX($textMatch, DqlExpressions::idIn($attributeValueAlias . '.id', $selectValueIds));

            // A correlated count per filter rather than joins, or an EXISTS that MySQL turns back into
            // joins: with ten filters, MySQL's join order search never ends.
            $qb->andWhere(sprintf(
                '(SELECT COUNT(%s.id) FROM %s %s JOIN %s.attribute %s WHERE %s.subject = %s AND %s.code = :%s AND (%s)) > 0',
                $attributeValueAlias,
                $this->associationClass($this->productClass, 'attributes'),
                $attributeValueAlias,
                $attributeValueAlias,
                $attributeAlias,
                $attributeValueAlias,
                $rootAlias,
                $attributeAlias,
                $codeParam,
                $valueMatch,
            ));

            ++$attributeIndex;
        }

        if ($filters['search'] !== '') {
            // Same as the "search" filter of the Sylius shop product grid: name, current locale.
            $qb
                ->join($rootAlias . '.translations', 'adv_search_translation', 'WITH', 'adv_search_translation.locale = :adv_search_locale')
                ->andWhere(sprintf("adv_search_translation.name LIKE :adv_search ESCAPE '%s'", DqlExpressions::LIKE_ESCAPE))
                ->setParameter('adv_search_locale', $localeCode)
                ->setParameter('adv_search', DqlExpressions::containsPattern($filters['search']));
        }

        $optionFilters = array_diff_key(array_filter($filters['options']), $excludedOptionCodes);
        $min = $excludePrice ? null : $filters['price']['min'];
        $max = $excludePrice ? null : $filters['price']['max'];

        if ($optionFilters !== [] || $min !== null || $max !== null) {
            $variantAlias ??= $this->joinEnabledVariant($qb, 'adv_pv', $rootAlias);
        }

        $optionIndex = 0;
        foreach ($optionFilters as $optionCode => $values) {
            $optionValueAlias = 'adv_pov_' . $optionIndex;
            $optionAlias = 'adv_po_' . $optionIndex;
            $optionTranslationAlias = 'adv_povt_' . $optionIndex;
            $codeParam = 'adv_opt_code_' . $optionIndex;
            $valuesParam = 'adv_opt_values_' . $optionIndex;

            $variantSubAlias = 'adv_spv_' . $optionIndex;

            // On the same variant as the price and the other options, through a correlated count for
            // the same reason as the attributes.
            $qb
                ->andWhere(sprintf(
                    '(SELECT COUNT(%3$s.id) FROM %1$s %2$s JOIN %2$s.optionValues %3$s JOIN %3$s.option %4$s JOIN %3$s.translations %5$s WITH %5$s.locale = :adv_opt_locale_%6$d WHERE %2$s = %7$s AND %4$s.code = :%8$s AND %5$s.value IN (:%9$s)) > 0',
                    $this->associationClass($this->productClass, 'variants'),
                    $variantSubAlias,
                    $optionValueAlias,
                    $optionAlias,
                    $optionTranslationAlias,
                    $optionIndex,
                    $variantAlias,
                    $codeParam,
                    $valuesParam,
                ))
                ->setParameter('adv_opt_locale_' . $optionIndex, $localeCode)
                ->setParameter($codeParam, $optionCode)
                ->setParameter($valuesParam, $values);

            ++$optionIndex;
        }

        if (!$excludeTaxons && $filters['taxons'] !== []) {
            $qb
                ->join($rootAlias . '.productTaxons', 'adv_ptx_taxon')
                ->join('adv_ptx_taxon.taxon', 'adv_tx_taxon')
                ->andWhere('adv_tx_taxon.id IN (:adv_taxon_ids)')
                ->setParameter('adv_taxon_ids', $filters['taxons']);
        }

        if ($min !== null || $max !== null) {
            $this->joinChannelPricing($qb, (string) $variantAlias, 'adv_cp_price');

            if ($min !== null) {
                $qb->andWhere('adv_cp_price.price >= :adv_price_min')->setParameter('adv_price_min', $min);
            }

            if ($max !== null) {
                $qb->andWhere('adv_cp_price.price <= :adv_price_max')->setParameter('adv_price_max', $max);
            }
        }
    }

    /**
     * @param class-string $class
     *
     * @return class-string
     */
    private function associationClass(string $class, string $association): string
    {
        return $this->em->getClassMetadata($class)->getAssociationTargetClass($association);
    }

    private function joinEnabledVariant(QueryBuilder $qb, string $alias, string $rootAlias = 'p'): string
    {
        $qb
            ->join($rootAlias . '.variants', $alias, 'WITH', $alias . '.enabled = :' . $alias . '_enabled')
            ->setParameter($alias . '_enabled', true);

        return $alias;
    }

    /**
     * Prices are channel scoped through the `channelCode` of the channel pricings. Without a
     * current channel (CLI), every channel pricing is considered.
     */
    private function joinChannelPricing(QueryBuilder $qb, string $variantAlias, string $alias): void
    {
        $qb->join($variantAlias . '.channelPricings', $alias);

        $channelCode = $this->getCurrentChannel()?->getCode();
        if ($channelCode !== null) {
            $qb->andWhere($alias . '.channelCode = :' . $alias . '_channel_code')->setParameter($alias . '_channel_code', $channelCode);
        }
    }

    /**
     * Restricts the storefront queries to the products available in the current channel.
     */
    private function applyCurrentChannel(QueryBuilder $qb): void
    {
        $channel = $this->getCurrentChannel();
        if ($channel !== null) {
            $qb->andWhere(':current_channel MEMBER OF p.channels')->setParameter('current_channel', $channel);
        }
    }

    /**
     * Products listed on a taxon page, as the Sylius shop product grid selects them: enabled, in the
     * current channel, and assigned to the taxon (or to one of its descendants).
     */
    private function createBaseProductsQb(AdvancedTaxonInterface $taxon, bool $includeChildren, string $localeCode): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p.id')
            ->from($this->productClass, 'p')
            // As the Sylius grid: a product without a translation in the shop locale is not listed.
            ->join('p.translations', 'base_translation', 'WITH', 'base_translation.locale = :base_locale')
            ->setParameter('base_locale', $localeCode)
            ->join('p.productTaxons', 'base_ptx')
            ->join('base_ptx.taxon', 'base_tx')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('enabled', true);

        $this->applyCurrentChannel($qb);

        if (
            $includeChildren &&
            $taxon->getLeft() !== null &&
            $taxon->getRight() !== null &&
            $taxon->getRoot() !== null
        ) {
            $qb
                ->andWhere('base_tx.root = :base_root_taxon')
                ->andWhere('base_tx.left >= :base_left_boundary')
                ->andWhere('base_tx.right <= :base_right_boundary')
                ->setParameter('base_root_taxon', $taxon->getRoot())
                ->setParameter('base_left_boundary', $taxon->getLeft())
                ->setParameter('base_right_boundary', $taxon->getRight());
        } else {
            $qb
                ->andWhere('base_tx = :base_taxon')
                ->setParameter('base_taxon', $taxon);
        }

        return $qb;
    }

    /**
     * @param NormalizedFilters $filters
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function getAttributeFacets(AdvancedTaxonInterface $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $selectedCodes = array_keys($filters['attributes']);

        // Attributes without a selected value all share the exact same constraints (only the other
        // selected attributes are filtered), so they are aggregated in a single grouped query
        // instead of running one query per attribute code.
        $grouped = $this->queryAttributeFacetValues(
            $taxon,
            $localeCode,
            $includeChildren,
            $filters,
            $selectedCodes === [] ? null : $selectedCodes,
        );

        // A selected attribute needs its own query: its own selection has to be ignored so that the
        // counts of the alternative values remain meaningful.
        foreach ($selectedCodes as $code) {
            $selected = $this->queryAttributeFacetValues(
                $taxon,
                $localeCode,
                $includeChildren,
                $filters,
                null,
                $code,
            );

            if (isset($selected[$code])) {
                $grouped[$code] = $selected[$code];
            }
        }

        return $this->sortFacetGroups($grouped);
    }

    /**
     * @param NormalizedFilters $filters
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function getOptionFacets(AdvancedTaxonInterface $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $selectedCodes = array_keys($filters['options']);

        // Same optimization as the attributes: options without a selected value share their
        // constraints and are aggregated together.
        $grouped = $this->queryOptionFacetValues(
            $taxon,
            $localeCode,
            $includeChildren,
            $filters,
            $selectedCodes === [] ? null : $selectedCodes,
        );

        foreach ($selectedCodes as $code) {
            $selected = $this->queryOptionFacetValues(
                $taxon,
                $localeCode,
                $includeChildren,
                $filters,
                null,
                $code,
            );

            if (isset($selected[$code])) {
                $grouped[$code] = $selected[$code];
            }
        }

        return $this->sortFacetGroups($grouped);
    }

    /**
     * @param NormalizedFilters $filters
     *
     * @return array<int, array{id: int, code: string, label: string, count: int, active: bool}>
     */
    private function getTaxonFacets(AdvancedTaxonInterface $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren, $localeCode);
        $qb
            ->resetDQLPart('select')
            ->select('adv_filter_taxon.id AS id')
            ->addSelect('adv_filter_taxon.code AS code')
            ->addSelect('COALESCE(adv_filter_taxon_translation.name, adv_filter_taxon.code) AS label')
            ->addSelect('COUNT(DISTINCT p.id) AS productCount')
            ->join('p.productTaxons', 'adv_filter_product_taxon')
            ->join('adv_filter_product_taxon.taxon', 'adv_filter_taxon', 'WITH', 'adv_filter_taxon.enabled = true')
            ->leftJoin('adv_filter_taxon.translations', 'adv_filter_taxon_translation', 'WITH', 'adv_filter_taxon_translation.locale = :adv_filter_taxon_locale')
            ->setParameter('adv_filter_taxon_locale', $localeCode)
            ->groupBy('adv_filter_taxon.id, adv_filter_taxon.code, adv_filter_taxon_translation.name');

        if (
            $taxon->getLeft() !== null &&
            $taxon->getRight() !== null &&
            $taxon->getRoot() !== null
        ) {
            $qb
                ->andWhere('adv_filter_taxon.root = :adv_filter_root_taxon')
                ->andWhere('adv_filter_taxon.left > :adv_filter_left_boundary')
                ->andWhere('adv_filter_taxon.right < :adv_filter_right_boundary')
                ->setParameter('adv_filter_root_taxon', $taxon->getRoot())
                ->setParameter('adv_filter_left_boundary', $taxon->getLeft())
                ->setParameter('adv_filter_right_boundary', $taxon->getRight());
        }

        $this->applyAdvancedFilters($qb, $filters, $localeCode, ['excludeTaxons' => true]);

        /** @var array<int, array{id: string|int, code: string, label: string, productCount: string|int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $selectedTaxons = $filters['taxons'];
        $facets = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($id <= 0) {
                continue;
            }

            $facets[] = [
                'id' => $id,
                'code' => (string) $row['code'],
                'label' => (string) $row['label'],
                'count' => (int) $row['productCount'],
                'active' => in_array($id, $selectedTaxons, true),
            ];
        }
        usort($facets, static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));

        return $facets;
    }

    /**
     * @param NormalizedFilters $filters
     *
     * @return array{min: float|null, max: float|null, selectedMin: float|null, selectedMax: float|null}
     */
    private function getPriceFacet(AdvancedTaxonInterface $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren, $localeCode);
        $qb
            ->resetDQLPart('select')
            ->select('MIN(adv_price_channel_pricing.price) AS minPrice')
            ->addSelect('MAX(adv_price_channel_pricing.price) AS maxPrice');

        $variantAlias = $this->joinEnabledVariant($qb, 'adv_price_variant');
        $this->joinChannelPricing($qb, $variantAlias, 'adv_price_channel_pricing');
        $this->applyAdvancedFilters($qb, $filters, $localeCode, ['excludePrice' => true], $variantAlias);

        /** @var array{minPrice: string|int|float|null, maxPrice: string|int|float|null}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        $min = isset($row['minPrice']) ? ((float) $row['minPrice']) / 100 : null;
        $max = isset($row['maxPrice']) ? ((float) $row['maxPrice']) / 100 : null;
        $selectedMin = $filters['price']['min'] !== null ? ((float) $filters['price']['min']) / 100 : null;
        $selectedMax = $filters['price']['max'] !== null ? ((float) $filters['price']['max']) / 100 : null;

        return [
            'min' => $min,
            'max' => $max,
            'selectedMin' => $selectedMin,
            'selectedMax' => $selectedMax,
        ];
    }

    /**
     * Unavailable in CLI contexts (for example while synchronizing conditional taxons).
     */
    private function getCurrentChannel(): ?ChannelInterface
    {
        try {
            return $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }
    }

    /**
     * Aggregates the values and the product counts of a set of attributes: text values in SQL,
     * select values (choice keys stored as JSON) in PHP.
     *
     * @param NormalizedFilters $filters
     * @param array<int, string>|null $excludeCodes attribute codes left out of the aggregation
     * @param string|null             $onlyCode     aggregate a single attribute code
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function queryAttributeFacetValues(
        AdvancedTaxonInterface $taxon,
        string $localeCode,
        bool $includeChildren,
        array $filters,
        ?array $excludeCodes,
        ?string $onlyCode = null,
    ): array {
        $createQb = function (?string $aggregate) use ($taxon, $localeCode, $includeChildren, $filters, $excludeCodes, $onlyCode): QueryBuilder {
            $qb = $this->createBaseProductsQb($taxon, $includeChildren, $localeCode);
            $qb
                ->resetDQLPart('select')
                ->select('adv_attr.code AS code')
                ->addSelect('COALESCE(adv_attr_translation.name, adv_attr.code) AS label')
                ->join('p.attributes', 'adv_attr_value')
                ->join('adv_attr_value.attribute', 'adv_attr')
                ->leftJoin('adv_attr.translations', 'adv_attr_translation', 'WITH', 'adv_attr_translation.locale = :adv_attr_locale')
                ->andWhere('adv_attr_value.localeCode = :adv_attr_locale OR adv_attr_value.localeCode IS NULL')
                ->setParameter('adv_attr_locale', $localeCode);

            if ($aggregate === 'text') {
                $qb
                    ->addSelect('adv_attr_value.text AS value')
                    ->addSelect('COUNT(DISTINCT p.id) AS productCount')
                    ->andWhere('adv_attr_value.text IS NOT NULL')
                    ->andWhere("adv_attr_value.text != ''")
                    ->groupBy('adv_attr.code, adv_attr_translation.name, adv_attr_value.text');
            } else {
                // JSON columns cannot be grouped on every platform: one row per product and value.
                $qb
                    ->addSelect('p.id AS productId')
                    ->addSelect('adv_attr_value.json AS json')
                    ->andWhere('adv_attr_value.json IS NOT NULL');
            }

            if ($excludeCodes !== null && $excludeCodes !== []) {
                $qb
                    ->andWhere('adv_attr.code NOT IN (:adv_facet_exclude_codes)')
                    ->setParameter('adv_facet_exclude_codes', $excludeCodes);
            }

            if ($onlyCode !== null) {
                $qb
                    ->andWhere('adv_attr.code = :adv_facet_only_code')
                    ->setParameter('adv_facet_only_code', $onlyCode);
            }

            $this->applyAdvancedFilters(
                $qb,
                $filters,
                $localeCode,
                $onlyCode !== null ? ['excludeAttributeCodes' => [$onlyCode]] : [],
            );

            return $qb;
        };

        /** @var array<int, array{code: string, label: string, value: string, productCount: string|int}> $rows */
        $rows = $createQb('text')->getQuery()->getArrayResult();

        /** @var array<int, array{code: string, label: string, productId: int|string, json: mixed}> $selectRows */
        $selectRows = $createQb(null)->getQuery()->getArrayResult();

        $productsByChoice = [];
        $choiceLabels = [];
        $facetLabels = [];
        foreach ($selectRows as $row) {
            $code = (string) $row['code'];
            $facetLabels[$code] = (string) $row['label'];
            $choiceLabels[$code] ??= $this->selectAttributeValues->choiceLabelsOf($code);

            foreach (is_array($row['json']) ? $row['json'] : [] as $key) {
                if (is_string($key) && $key !== '') {
                    $productsByChoice[$code][$key][(string) $row['productId']] = true;
                }
            }
        }

        foreach ($productsByChoice as $code => $choices) {
            foreach ($choices as $key => $products) {
                $translations = $choiceLabels[$code][$key] ?? [];
                $rows[] = [
                    'code' => $code,
                    'label' => $facetLabels[$code],
                    'value' => $key,
                    'valueLabel' => $translations[$localeCode] ?? (reset($translations) ?: $key),
                    'productCount' => count($products),
                ];
            }
        }

        return $this->groupFacetRows($rows, $filters['attributes']);
    }

    /**
     * Aggregates the values and the product counts of a set of product options in a single query.
     *
     * @param NormalizedFilters $filters
     * @param array<int, string>|null $excludeCodes option codes left out of the aggregation
     * @param string|null             $onlyCode     aggregate a single option code
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function queryOptionFacetValues(
        AdvancedTaxonInterface $taxon,
        string $localeCode,
        bool $includeChildren,
        array $filters,
        ?array $excludeCodes,
        ?string $onlyCode = null,
    ): array {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren, $localeCode);
        $variantAlias = $this->joinEnabledVariant($qb, 'adv_option_variant');
        $qb
            ->resetDQLPart('select')
            ->select('adv_option.code AS code')
            ->addSelect('COALESCE(adv_option_name.name, adv_option.code) AS label')
            ->addSelect('adv_option_translation.value AS value')
            ->addSelect('COUNT(DISTINCT p.id) AS productCount')
            ->join($variantAlias . '.optionValues', 'adv_option_value')
            ->join('adv_option_value.option', 'adv_option')
            ->leftJoin('adv_option.translations', 'adv_option_name', 'WITH', 'adv_option_name.locale = :adv_option_locale')
            ->join('adv_option_value.translations', 'adv_option_translation', 'WITH', 'adv_option_translation.locale = :adv_option_locale')
            ->andWhere("adv_option_translation.value != ''")
            ->setParameter('adv_option_locale', $localeCode)
            ->groupBy('adv_option.code, adv_option_name.name, adv_option_translation.value')
            ->orderBy('adv_option.code', 'ASC')
            ->addOrderBy('adv_option_translation.value', 'ASC');

        if ($excludeCodes !== null && $excludeCodes !== []) {
            $qb
                ->andWhere('adv_option.code NOT IN (:adv_facet_exclude_option_codes)')
                ->setParameter('adv_facet_exclude_option_codes', $excludeCodes);
        }

        if ($onlyCode !== null) {
            $qb
                ->andWhere('adv_option.code = :adv_facet_only_option_code')
                ->setParameter('adv_facet_only_option_code', $onlyCode);
        }

        $this->applyAdvancedFilters(
            $qb,
            $filters,
            $localeCode,
            $onlyCode !== null ? ['excludeOptionCodes' => [$onlyCode]] : [],
            $variantAlias,
        );

        /** @var array<int, array{code: string, label: string, value: string, productCount: string|int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $this->groupFacetRows($rows, $filters['options']);
    }

    /**
     * Groups flat facet rows (one row per value) by facet code, values sorted by label.
     *
     * @param array<int, array{code: string, label: string, value: string, valueLabel?: string, productCount: string|int}> $rows
     * @param array<string, array<int, string>> $selectedValuesByCode
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function groupFacetRows(array $rows, array $selectedValuesByCode): array
    {
        /** @var array<string, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $value = trim((string) $row['value']);

            if ($code === '' || $value === '') {
                continue;
            }

            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'code' => $code,
                    'label' => (string) $row['label'],
                    'values' => [],
                ];
            }

            // Text values differing only by surrounding spaces are one value (the filter trims too).
            if (isset($grouped[$code]['values'][$value])) {
                $grouped[$code]['values'][$value]['count'] += (int) $row['productCount'];

                continue;
            }

            $grouped[$code]['values'][$value] = [
                'value' => $value,
                'label' => $row['valueLabel'] ?? $value,
                'count' => (int) $row['productCount'],
                'active' => in_array($value, $selectedValuesByCode[$code] ?? [], true),
            ];
        }

        foreach ($grouped as &$group) {
            usort($group['values'], static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
        }

        return $grouped;
    }

    /**
     * @param array<string, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}> $grouped
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     */
    private function sortFacetGroups(array $grouped): array
    {
        uasort($grouped, static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));

        return array_values($grouped);
    }
}
