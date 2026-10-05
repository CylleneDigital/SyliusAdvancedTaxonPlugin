<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds a Doctrine QueryBuilder that returns products matching
 * all facet conditions defined on a virtual/conditional taxon.
 *
 * Conditions are combined with AND (all must match).
 * If a product has at least one variant matching a condition, it is included.
 *
 * Negative operators (`not_equals`, `not_contains`, `not_in`) are compiled to `NOT EXISTS`
 * subqueries: a product must own no value matching the excluded one. A plain `!=` on a joined
 * alias would be wrong as soon as a product owns several values (variants, taxons, ...).
 */
final class FacetProductQueryBuilder
{
    /**
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%sylius.model.product.class%')]
        private readonly string $productClass,
        #[Autowire(service: 'sylius.context.channel')]
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function buildQuery(Taxon $taxon, string $localeCode): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p')
            ->from($this->productClass, 'p')
            ->join('p.translations', 'pt', 'WITH', 'pt.locale = :locale')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('locale', $localeCode)
            ->setParameter('enabled', true);

        foreach ($taxon->getFacetConditions() as $idx => $condition) {
            $this->applyCondition($qb, $condition, $idx);
        }

        return $qb;
    }

    public function buildTaxonProductsQuery(Taxon $taxon, string $localeCode, bool $includeChildren = false): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p')
            ->from($this->productClass, 'p')
            ->leftJoin('p.productTaxons', 'ptx')
            ->leftJoin('ptx.taxon', 'tx')
            ->leftJoin('p.mainTaxon', 'mainTx')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.id', 'DESC');

        if (
            $includeChildren &&
            $taxon->getLeft() !== null &&
            $taxon->getRight() !== null &&
            $taxon->getRoot() !== null
        ) {
            $qb
                ->andWhere('(tx.root = :rootTaxon AND tx.left >= :leftBoundary AND tx.right <= :rightBoundary) OR (mainTx.root = :rootTaxon AND mainTx.left >= :leftBoundary AND mainTx.right <= :rightBoundary)')
                ->setParameter('rootTaxon', $taxon->getRoot())
                ->setParameter('leftBoundary', $taxon->getLeft())
                ->setParameter('rightBoundary', $taxon->getRight());
        } else {
            $qb
                ->andWhere('tx = :taxon OR mainTx = :taxon')
                ->setParameter('taxon', $taxon);
        }

        return $qb;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildAdvancedFilteredTaxonProductsQuery(
        Taxon $taxon,
        string $localeCode,
        bool $includeChildren = false,
        array $filters = [],
    ): QueryBuilder {
        $normalizedFilters = $this->normalizeAdvancedFilters($filters);

        $qb = $this->buildTaxonProductsQuery($taxon, $localeCode, $includeChildren);

        $this->applyAdvancedFilters($qb, $normalizedFilters, $localeCode);

        return $qb;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   selected: array<string, mixed>,
     *   facets: array{
     *     attributes: array<int, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>,
     *     options: array<int, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>,
     *     taxons: array<int, array{id: int, code: string, label: string, count: int, active: bool}>,
     *     price: array{min: float|null, max: float|null, selectedMin: float|null, selectedMax: float|null}
     *   }
     * }
     */
    public function getAdvancedFiltersData(
        Taxon $taxon,
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
     * @param array<int, array{conditionType?: string, operator?: string, referenceCode?: string|null, value?: string|null}> $conditions
     *
     * @return array{count: int, products: array<int, object>}
     */
    public function previewConditions(array $conditions, string $localeCode, int $limit = 20): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p')
            ->from($this->productClass, 'p')
            ->join('p.translations', 'pt', 'WITH', 'pt.locale = :locale')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('locale', $localeCode)
            ->setParameter('enabled', true);

        foreach ($conditions as $idx => $data) {
            $conditionType = (string) ($data['conditionType'] ?? '');
            $operator = (string) ($data['operator'] ?? '');

            if ($conditionType === '' || $operator === '') {
                continue;
            }

            if (!array_key_exists($conditionType, FacetCondition::OPERATORS_BY_TYPE)) {
                continue;
            }

            if (!in_array($operator, FacetCondition::OPERATORS_BY_TYPE[$conditionType], true)) {
                continue;
            }

            $condition = new FacetCondition();
            $condition->setConditionType($conditionType);
            $condition->setOperator($operator);
            $condition->setReferenceCode($data['referenceCode'] ?? null);
            $condition->setValue($data['value'] ?? null);

            $this->applyCondition($qb, $condition, $idx);
        }

        $countQb = clone $qb;
        $count = (int) $countQb
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var array<int, object> $products */
        $products = $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'count' => $count,
            'products' => $products,
        ];
    }

    private function applyCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        switch ($condition->getConditionType()) {
            case FacetCondition::TYPE_NAME:
                $this->applyStringCondition($qb, 'pt.name', $condition, 'f_name_' . $idx);

                break;
            case FacetCondition::TYPE_DESCRIPTION:
                $this->applyStringCondition($qb, 'pt.description', $condition, 'f_desc_' . $idx);

                break;
            case FacetCondition::TYPE_STOCK:
                $this->applyStockCondition($qb, $idx);

                break;
            case FacetCondition::TYPE_ATTRIBUTE:
                $this->applyAttributeCondition($qb, $condition, $idx);

                break;
            case FacetCondition::TYPE_OPTION:
                $this->applyOptionCondition($qb, $condition, $idx);

                break;
            case FacetCondition::TYPE_TAXON:
                $this->applyTaxonCondition($qb, $condition, $idx);

                break;
        }
    }

    /**
     * A product is in stock when at least one of its variants is either tracked with stock on hand,
     * or not tracked at all.
     */
    private function applyStockCondition(QueryBuilder $qb, int $idx): void
    {
        $variantAlias = 'f_stock_variant_' . $idx;
        $trackedParam = 'f_stock_tracked_' . $idx;

        $qb
            ->join('p.variants', $variantAlias)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->gt($variantAlias . '.onHand', 0),
                $qb->expr()->eq($variantAlias . '.tracked', ':' . $trackedParam),
            ))
            ->setParameter($trackedParam, false);
    }

    /**
     * The product translation alias `pt` is joined on a single locale, so a plain comparison is
     * exact here; negative operators still have to accept products whose field is empty.
     */
    private function applyStringCondition(QueryBuilder $qb, string $field, FacetCondition $condition, string $param): void
    {
        $value = $condition->getValue() ?? '';

        match ($condition->getOperator()) {
            'equals' => $qb->andWhere("$field = :$param")->setParameter($param, $value),
            'not_equals' => $qb
                ->andWhere($qb->expr()->orX("$field != :$param", "$field IS NULL"))
                ->setParameter($param, $value),
            'contains' => $qb->andWhere("$field LIKE :$param")->setParameter($param, '%' . $value . '%'),
            'not_contains' => $qb
                ->andWhere($qb->expr()->orX("$field NOT LIKE :$param", "$field IS NULL"))
                ->setParameter($param, '%' . $value . '%'),
            default => null,
        };
    }

    private function applyAttributeCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        $referenceCode = $condition->getReferenceCode();

        if ($referenceCode === null || $referenceCode === '') {
            return;
        }

        $referenceParam = 'f_attr_code_' . $idx;
        $valueParam = 'f_attr_value_' . $idx;
        $value = $condition->getValue() ?? '';

        if (FacetCondition::isNegativeOperator($condition->getOperator())) {
            $subQb = $this->em->createQueryBuilder()
                ->select('1')
                ->from($this->productClass, 'sub_p_' . $idx)
                ->join('sub_p_' . $idx . '.attributes', 'sub_pav_' . $idx)
                ->join('sub_pav_' . $idx . '.attribute', 'sub_pa_' . $idx)
                ->andWhere('sub_p_' . $idx . ' = p')
                ->andWhere('sub_pa_' . $idx . '.code = :' . $referenceParam)
                ->setParameter($referenceParam, $referenceCode);

            $this->applyAttributeValueMatch(
                $subQb,
                'sub_pav_' . $idx,
                FacetCondition::negatedOperator($condition->getOperator()),
                $value,
                $valueParam,
            );

            $this->addNotExists($qb, $subQb);

            return;
        }

        $attributeValueAlias = 'f_pav_' . $idx;
        $attributeAlias = 'f_pa_' . $idx;

        $qb
            ->join('p.attributes', $attributeValueAlias)
            ->join($attributeValueAlias . '.attribute', $attributeAlias)
            ->andWhere($attributeAlias . '.code = :' . $referenceParam)
            ->setParameter($referenceParam, $referenceCode);

        $this->applyAttributeValueMatch($qb, $attributeValueAlias, $condition->getOperator(), $value, $valueParam);
    }

    /**
     * Sylius stores attribute values in type specific fields (text, integer, float, boolean, json).
     *
     * String comparisons target the `text` field, and also the `json` field used by select and
     * multi-select attributes which store their values as JSON encoded strings
     * (for example '["some_value"]').
     *
     * This method only handles the positive operators used to detect a match; negations are
     * expressed by wrapping the same constraint into a `NOT EXISTS` subquery.
     */
    private function applyAttributeValueMatch(QueryBuilder $qb, string $alias, string $operator, string $value, string $param): void
    {
        match ($operator) {
            'equals' => $qb
                ->andWhere($qb->expr()->orX("$alias.text = :$param", "$alias.json LIKE :json_$param"))
                ->setParameter($param, $value)
                ->setParameter('json_' . $param, '%"' . addcslashes($value, '"\\') . '"%'),
            'contains' => $qb
                ->andWhere($qb->expr()->orX("$alias.text LIKE :$param", "$alias.json LIKE :json_$param"))
                ->setParameter($param, '%' . $value . '%')
                ->setParameter('json_' . $param, '%' . $value . '%'),
            default => null,
        };
    }

    private function applyOptionCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        $referenceCode = $condition->getReferenceCode();

        if ($referenceCode === null || $referenceCode === '') {
            return;
        }

        $referenceParam = 'f_opt_code_' . $idx;
        $valueParam = 'f_opt_value_' . $idx;
        $value = $condition->getValue() ?? '';

        if (FacetCondition::isNegativeOperator($condition->getOperator())) {
            $subQb = $this->em->createQueryBuilder()
                ->select('1')
                ->from($this->productClass, 'sub_p_' . $idx)
                ->join('sub_p_' . $idx . '.variants', 'sub_opt_variant_' . $idx)
                ->join('sub_opt_variant_' . $idx . '.optionValues', 'sub_opt_value_' . $idx)
                ->join('sub_opt_value_' . $idx . '.option', 'sub_opt_option_' . $idx)
                ->join('sub_opt_value_' . $idx . '.translations', 'sub_opt_translation_' . $idx)
                ->andWhere('sub_p_' . $idx . ' = p')
                ->andWhere('sub_opt_option_' . $idx . '.code = :' . $referenceParam)
                ->andWhere('sub_opt_translation_' . $idx . '.value = :' . $valueParam)
                ->setParameter($referenceParam, $referenceCode)
                ->setParameter($valueParam, $value);

            $this->addNotExists($qb, $subQb);

            return;
        }

        $variantAlias = 'f_opt_variant_' . $idx;
        $optionValueAlias = 'f_opt_value_' . $idx;
        $optionAlias = 'f_opt_option_' . $idx;
        $translationAlias = 'f_opt_translation_' . $idx;

        $qb
            ->join('p.variants', $variantAlias)
            ->join($variantAlias . '.optionValues', $optionValueAlias)
            ->join($optionValueAlias . '.option', $optionAlias)
            ->join($optionValueAlias . '.translations', $translationAlias)
            ->andWhere($optionAlias . '.code = :' . $referenceParam)
            ->andWhere($translationAlias . '.value = :' . $valueParam)
            ->setParameter($referenceParam, $referenceCode)
            ->setParameter($valueParam, $value);
    }

    private function applyTaxonCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        $referenceCode = $condition->getReferenceCode();

        if ($referenceCode === null || $referenceCode === '') {
            return;
        }

        $referenceParam = 'f_taxon_code_' . $idx;

        if (FacetCondition::isNegativeOperator($condition->getOperator())) {
            $subQb = $this->em->createQueryBuilder()
                ->select('1')
                ->from($this->productClass, 'sub_p_' . $idx)
                ->join('sub_p_' . $idx . '.productTaxons', 'sub_ptx_' . $idx)
                ->join('sub_ptx_' . $idx . '.taxon', 'sub_taxon_' . $idx)
                ->andWhere('sub_p_' . $idx . ' = p')
                ->andWhere('sub_taxon_' . $idx . '.code = :' . $referenceParam)
                ->setParameter($referenceParam, $referenceCode);

            $this->addNotExists($qb, $subQb);

            return;
        }

        $taxonAlias = 'f_taxon_' . $idx;

        $qb
            ->join('p.productTaxons', 'f_ptx_' . $idx)
            ->join('f_ptx_' . $idx . '.taxon', $taxonAlias)
            ->andWhere($taxonAlias . '.code = :' . $referenceParam)
            ->setParameter($referenceParam, $referenceCode);
    }

    /**
     * Adds a `NOT EXISTS` constraint and forwards the subquery parameters to the outer query.
     */
    private function addNotExists(QueryBuilder $qb, QueryBuilder $subQueryBuilder): void
    {
        foreach ($subQueryBuilder->getParameters() as $parameter) {
            $qb->setParameter($parameter->getName(), $parameter->getValue());
        }

        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQueryBuilder->getDQL())));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * }
     */
    private function normalizeAdvancedFilters(array $filters): array
    {
        $normalizedAttributes = [];
        $attributes = $filters['attributes'] ?? [];
        if (is_array($attributes)) {
            foreach ($attributes as $code => $values) {
                if (!is_string($code) || $code === '') {
                    continue;
                }

                $normalizedValues = [];
                if (is_array($values)) {
                    foreach ($values as $value) {
                        if (!is_string($value)) {
                            continue;
                        }

                        $trimmed = trim($value);
                        if ($trimmed !== '') {
                            $normalizedValues[$trimmed] = $trimmed;
                        }
                    }
                }

                if ($normalizedValues !== []) {
                    $normalizedAttributes[$code] = array_values($normalizedValues);
                }
            }
        }

        $normalizedOptions = [];
        $options = $filters['options'] ?? [];
        if (is_array($options)) {
            foreach ($options as $code => $values) {
                if (!is_string($code) || $code === '') {
                    continue;
                }

                $normalizedValues = [];
                if (is_array($values)) {
                    foreach ($values as $value) {
                        if (!is_string($value)) {
                            continue;
                        }

                        $trimmed = trim($value);
                        if ($trimmed !== '') {
                            $normalizedValues[$trimmed] = $trimmed;
                        }
                    }
                }

                if ($normalizedValues !== []) {
                    $normalizedOptions[$code] = array_values($normalizedValues);
                }
            }
        }

        $normalizedTaxons = [];
        $taxons = $filters['taxons'] ?? [];
        if (is_array($taxons)) {
            foreach ($taxons as $taxonId) {
                if (!is_scalar($taxonId)) {
                    continue;
                }

                $id = (int) $taxonId;
                if ($id > 0) {
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
        ];
    }

    /**
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     * @param array{
     *   excludeAttributeCodes?: array<int, string>,
     *   excludeOptionCodes?: array<int, string>,
     *   excludeTaxons?: bool,
     *   excludePrice?: bool
     * } $exclusions
     */
    private function applyAdvancedFilters(QueryBuilder $qb, array $filters, string $localeCode, array $exclusions = []): void
    {
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

            $qb
                ->join('p.attributes', $attributeValueAlias)
                ->join($attributeValueAlias . '.attribute', $attributeAlias)
                ->andWhere($attributeAlias . '.code = :' . $codeParam)
                ->andWhere($attributeValueAlias . '.text IN (:' . $valuesParam . ')')
                ->setParameter($codeParam, $attributeCode)
                ->setParameter($valuesParam, $values);

            ++$attributeIndex;
        }

        $optionIndex = 0;
        foreach ($filters['options'] as $optionCode => $values) {
            if ($values === [] || isset($excludedOptionCodes[$optionCode])) {
                continue;
            }

            $variantAlias = 'adv_pv_opt_' . $optionIndex;
            $optionValueAlias = 'adv_pov_' . $optionIndex;
            $optionAlias = 'adv_po_' . $optionIndex;
            $optionTranslationAlias = 'adv_povt_' . $optionIndex;
            $codeParam = 'adv_opt_code_' . $optionIndex;
            $valuesParam = 'adv_opt_values_' . $optionIndex;

            $qb
                ->join('p.variants', $variantAlias)
                ->join($variantAlias . '.optionValues', $optionValueAlias)
                ->join($optionValueAlias . '.option', $optionAlias)
                ->join($optionValueAlias . '.translations', $optionTranslationAlias, 'WITH', $optionTranslationAlias . '.locale = :adv_opt_locale_' . $optionIndex)
                ->andWhere($optionAlias . '.code = :' . $codeParam)
                ->andWhere($optionTranslationAlias . '.value IN (:' . $valuesParam . ')')
                ->setParameter('adv_opt_locale_' . $optionIndex, $localeCode)
                ->setParameter($codeParam, $optionCode)
                ->setParameter($valuesParam, $values);

            ++$optionIndex;
        }

        if (!$excludeTaxons && $filters['taxons'] !== []) {
            $qb
                ->join('p.productTaxons', 'adv_ptx_taxon')
                ->join('adv_ptx_taxon.taxon', 'adv_tx_taxon')
                ->andWhere('adv_tx_taxon.id IN (:adv_taxon_ids)')
                ->setParameter('adv_taxon_ids', $filters['taxons']);
        }

        if (!$excludePrice) {
            $min = $filters['price']['min'];
            $max = $filters['price']['max'];
            if ($min !== null || $max !== null) {
                $qb
                    ->join('p.variants', 'adv_pv_price')
                    ->join('adv_pv_price.channelPricings', 'adv_cp_price');

                $channel = $this->getCurrentChannel();
                if ($channel !== null && $channel->getCode() !== null) {
                    $qb
                        ->andWhere('adv_cp_price.channelCode = :adv_price_channel_code')
                        ->setParameter('adv_price_channel_code', $channel->getCode());
                }

                if ($min !== null) {
                    $qb
                        ->andWhere('adv_cp_price.price >= :adv_price_min')
                        ->setParameter('adv_price_min', $min);
                }

                if ($max !== null) {
                    $qb
                        ->andWhere('adv_cp_price.price <= :adv_price_max')
                        ->setParameter('adv_price_max', $max);
                }
            }
        }
    }

    private function createBaseProductsQb(Taxon $taxon, bool $includeChildren): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p.id')
            ->from($this->productClass, 'p')
            ->leftJoin('p.productTaxons', 'base_ptx')
            ->leftJoin('base_ptx.taxon', 'base_tx')
            ->leftJoin('p.mainTaxon', 'base_main_tx')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('enabled', true);

        if (
            $includeChildren &&
            $taxon->getLeft() !== null &&
            $taxon->getRight() !== null &&
            $taxon->getRoot() !== null
        ) {
            $qb
                ->andWhere('(base_tx.root = :base_root_taxon AND base_tx.left >= :base_left_boundary AND base_tx.right <= :base_right_boundary) OR (base_main_tx.root = :base_root_taxon AND base_main_tx.left >= :base_left_boundary AND base_main_tx.right <= :base_right_boundary)')
                ->setParameter('base_root_taxon', $taxon->getRoot())
                ->setParameter('base_left_boundary', $taxon->getLeft())
                ->setParameter('base_right_boundary', $taxon->getRight());
        } else {
            $qb
                ->andWhere('base_tx = :base_taxon OR base_main_tx = :base_taxon')
                ->setParameter('base_taxon', $taxon);
        }

        return $qb;
    }

    /**
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function getAttributeFacets(Taxon $taxon, string $localeCode, bool $includeChildren, array $filters): array
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
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function getOptionFacets(Taxon $taxon, string $localeCode, bool $includeChildren, array $filters): array
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
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     *
     * @return array<int, array{id: int, code: string, label: string, count: int, active: bool}>
     */
    private function getTaxonFacets(Taxon $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren);
        $qb
            ->resetDQLPart('select')
            ->select('adv_filter_taxon.id AS id')
            ->addSelect('adv_filter_taxon.code AS code')
            ->addSelect('COALESCE(adv_filter_taxon_translation.name, adv_filter_taxon.code) AS label')
            ->addSelect('COUNT(DISTINCT p.id) AS productCount')
            ->join('p.productTaxons', 'adv_filter_product_taxon')
            ->join('adv_filter_product_taxon.taxon', 'adv_filter_taxon')
            ->leftJoin('adv_filter_taxon.translations', 'adv_filter_taxon_translation', 'WITH', 'adv_filter_taxon_translation.locale = :adv_filter_taxon_locale')
            ->setParameter('adv_filter_taxon_locale', $localeCode)
            ->groupBy('adv_filter_taxon.id, adv_filter_taxon.code, adv_filter_taxon_translation.name')
            ->orderBy('label', 'ASC');

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

        return $facets;
    }

    /**
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     *
     * @return array{min: float|null, max: float|null, selectedMin: float|null, selectedMax: float|null}
     */
    private function getPriceFacet(Taxon $taxon, string $localeCode, bool $includeChildren, array $filters): array
    {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren);
        $qb
            ->resetDQLPart('select')
            ->select('MIN(adv_price_channel_pricing.price) AS minPrice')
            ->addSelect('MAX(adv_price_channel_pricing.price) AS maxPrice')
            ->join('p.variants', 'adv_price_variant')
            ->join('adv_price_variant.channelPricings', 'adv_price_channel_pricing');

        $channel = $this->getCurrentChannel();
        if ($channel !== null && $channel->getCode() !== null) {
            $qb
                ->andWhere('adv_price_channel_pricing.channelCode = :adv_price_facet_channel_code')
                ->setParameter('adv_price_facet_channel_code', $channel->getCode());
        }

        $this->applyAdvancedFilters($qb, $filters, $localeCode, ['excludePrice' => true]);

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
     * Prices are channel scoped in Sylius through the `channelCode` field of channel pricings.
     * The current channel is unavailable in CLI contexts (for example while synchronizing
     * conditional taxons), in which case no channel filter is applied so that every channel
     * pricing is considered.
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
     * Aggregates the values and the product counts of a set of attributes in a single query.
     *
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     * @param array<int, string>|null $excludeCodes attribute codes left out of the aggregation
     * @param string|null             $onlyCode     aggregate a single attribute code
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function queryAttributeFacetValues(
        Taxon $taxon,
        string $localeCode,
        bool $includeChildren,
        array $filters,
        ?array $excludeCodes,
        ?string $onlyCode = null,
    ): array {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren);
        $qb
            ->resetDQLPart('select')
            ->select('adv_attr.code AS code')
            ->addSelect('COALESCE(adv_attr_translation.name, adv_attr.code) AS label')
            ->addSelect('adv_attr_value.text AS value')
            ->addSelect('COUNT(DISTINCT p.id) AS productCount')
            ->join('p.attributes', 'adv_attr_value')
            ->join('adv_attr_value.attribute', 'adv_attr')
            ->leftJoin('adv_attr.translations', 'adv_attr_translation', 'WITH', 'adv_attr_translation.locale = :adv_attr_locale')
            ->andWhere('adv_attr_value.text IS NOT NULL')
            ->andWhere("adv_attr_value.text != ''")
            ->setParameter('adv_attr_locale', $localeCode)
            ->groupBy('adv_attr.code, adv_attr_translation.name, adv_attr_value.text')
            ->orderBy('adv_attr.code', 'ASC')
            ->addOrderBy('adv_attr_value.text', 'ASC');

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

        /** @var array<int, array{code: string, label: string, value: string, productCount: string|int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $this->groupFacetRows($rows, $filters['attributes']);
    }

    /**
     * Aggregates the values and the product counts of a set of product options in a single query.
     *
     * @param array{
     *   attributes: array<string, array<int, string>>,
     *   options: array<string, array<int, string>>,
     *   taxons: array<int, int>,
     *   price: array{min: int|null, max: int|null}
     * } $filters
     * @param array<int, string>|null $excludeCodes option codes left out of the aggregation
     * @param string|null             $onlyCode     aggregate a single option code
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function queryOptionFacetValues(
        Taxon $taxon,
        string $localeCode,
        bool $includeChildren,
        array $filters,
        ?array $excludeCodes,
        ?string $onlyCode = null,
    ): array {
        $qb = $this->createBaseProductsQb($taxon, $includeChildren);
        $qb
            ->resetDQLPart('select')
            ->select('adv_option.code AS code')
            ->addSelect('adv_option.code AS label')
            ->addSelect('adv_option_translation.value AS value')
            ->addSelect('COUNT(DISTINCT p.id) AS productCount')
            ->join('p.variants', 'adv_option_variant')
            ->join('adv_option_variant.optionValues', 'adv_option_value')
            ->join('adv_option_value.option', 'adv_option')
            ->join('adv_option_value.translations', 'adv_option_translation', 'WITH', 'adv_option_translation.locale = :adv_option_locale')
            ->andWhere("adv_option_translation.value != ''")
            ->setParameter('adv_option_locale', $localeCode)
            ->groupBy('adv_option.code, adv_option_translation.value')
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
        );

        /** @var array<int, array{code: string, label: string, value: string, productCount: string|int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $this->groupFacetRows($rows, $filters['options']);
    }

    /**
     * Groups flat facet rows (one row per value) by facet code.
     *
     * @param array<int, array{code: string, label: string, value: string, productCount: string|int}> $rows
     * @param array<string, array<int, string>>                                                       $selectedValuesByCode
     *
     * @return array<string, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function groupFacetRows(array $rows, array $selectedValuesByCode): array
    {
        /** @var array<string, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}> $grouped */
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

            $grouped[$code]['values'][] = [
                'value' => $value,
                'count' => (int) $row['productCount'],
                'active' => in_array($value, $selectedValuesByCode[$code] ?? [], true),
            ];
        }

        return $grouped;
    }

    /**
     * @param array<string, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}> $grouped
     *
     * @return array<int, array{code: string, label: string, values: array<int, array{value: string, count: int, active: bool}>}>
     */
    private function sortFacetGroups(array $grouped): array
    {
        uasort($grouped, static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));

        return array_values($grouped);
    }
}
