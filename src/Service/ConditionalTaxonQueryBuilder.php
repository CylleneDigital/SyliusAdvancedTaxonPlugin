<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Builds the query of the products matching the conditions of a conditional taxon, and the preview
 * of conditions being edited.
 *
 * Conditions are combined with AND; a product matches a condition when one of its values (variant,
 * attribute value, taxon) does. Negative operators (`not_equals`, `not_contains`, `not_in`) are
 * compiled to `NOT EXISTS` subqueries: a product must own no value matching the excluded one. A plain
 * `!=` on a joined alias would be wrong as soon as a product owns several values.
 *
 * A condition that cannot be applied (operator of another type, missing reference or value) makes
 * the whole query match nothing.
 */
final readonly class ConditionalTaxonQueryBuilder
{
    /**
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private EntityManagerInterface $em,
        private string $productClass,
        private FacetReferenceCheckerInterface $referenceChecker,
        private SelectAttributeValueResolver $selectAttributeValues,
    ) {
    }

    /**
     * Products matching the conditions of a taxon, enabled or not: a product disabled for a while
     * keeps its assignment and its position, the storefront only lists enabled products anyway.
     */
    public function buildQuery(AdvancedTaxonInterface $taxon, string $localeCode): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p')
            ->from($this->productClass, 'p')
            ->leftJoin('p.translations', 'pt', 'WITH', 'pt.locale = :locale')
            ->setParameter('locale', $localeCode);

        $conditions = $taxon->getFacetConditions()->toArray();
        $applicable = array_filter($conditions, $this->isApplicable(...));

        // Fail closed: an incomplete or inconsistent stored condition (API, fixtures, import) must
        // never widen the selection up to the whole catalog.
        if ($applicable === [] || count($applicable) !== count($conditions)) {
            return $qb->andWhere('1 = 0');
        }

        foreach ($applicable as $idx => $condition) {
            $this->applyCondition($qb, $condition, $idx);
        }

        return $qb;
    }

    /**
     * @param array<int, array{conditionType?: string, operator?: string, referenceCode?: string|null, value?: string|null}> $conditions
     *
     * @return array{count: int, products: list<array{id: int, code: string|null, name: string|null}>}
     */
    public function previewConditions(array $conditions, string $localeCode, int $limit = 20): array
    {
        // Same products as the synchronization will assign: enabled or not, names read in the same locale.
        // Scalar fields only: a DISTINCT on the whole entity fails on PostgreSQL for a JSON column.
        $qb = $this->em->createQueryBuilder();
        $qb
            ->select('DISTINCT p.id AS id', 'p.code AS code', 'pt.name AS name')
            ->from($this->productClass, 'p')
            ->leftJoin('p.translations', 'pt', 'WITH', 'pt.locale = :locale')
            ->setParameter('locale', $localeCode);

        $applied = 0;
        foreach ($conditions as $idx => $data) {
            $condition = new FacetCondition();
            $condition->setConditionType((string) ($data['conditionType'] ?? ''));
            $condition->setOperator((string) ($data['operator'] ?? ''));
            $condition->setReferenceCode($data['referenceCode'] ?? null);
            $condition->setValue($data['value'] ?? null);

            // Rows still being filled in the admin form are skipped, the complete ones are previewed.
            if (!$this->isApplicable($condition)) {
                continue;
            }

            $this->applyCondition($qb, $condition, $idx);
            ++$applied;
        }

        if ($applied === 0) {
            return ['count' => 0, 'products' => []];
        }

        $countQb = clone $qb;
        $count = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<array{id: int|string, code: string|null, name: string|null}> $rows */
        $rows = $qb
            ->orderBy('p.id')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return [
            'count' => $count,
            'products' => array_map(
                static fn (array $row): array => ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $row['name']],
                $rows,
            ),
        ];
    }

    private function isApplicable(FacetCondition $condition): bool
    {
        $type = $condition->getConditionType();
        $operators = FacetCondition::OPERATORS_BY_TYPE[$type] ?? [];

        if (!in_array($condition->getOperator(), $operators, true)) {
            return false;
        }

        // A taxon conditioned on its own membership would flip its products on every synchronization.
        if ($type === FacetCondition::TYPE_TAXON && $condition->getReferenceCode() === $condition->getTaxon()?->getCode()) {
            return false;
        }

        // A reference that does not exist (deleted, mistyped) would make a negative condition match
        // the whole catalog.
        if (FacetCondition::requiresReference($type) && !$this->referenceChecker->exists($type, trim((string) $condition->getReferenceCode()))) {
            return false;
        }

        return !FacetCondition::requiresValue($type) || trim((string) $condition->getValue()) !== '';
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
     * A product is in stock when at least one of its enabled variants is either not tracked, or
     * tracked with stock left once the reserved quantity is deducted (as Sylius checks it).
     */
    private function applyStockCondition(QueryBuilder $qb, int $idx): void
    {
        $variantAlias = 'f_stock_variant_' . $idx;
        $trackedParam = 'f_stock_tracked_' . $idx;
        $enabledParam = 'f_stock_enabled_' . $idx;

        $qb
            ->join('p.variants', $variantAlias, 'WITH', $variantAlias . '.enabled = :' . $enabledParam)
            ->andWhere($qb->expr()->orX(
                $variantAlias . '.onHand - ' . $variantAlias . '.onHold > 0',
                $qb->expr()->eq($variantAlias . '.tracked', ':' . $trackedParam),
            ))
            ->setParameter($enabledParam, true)
            ->setParameter($trackedParam, false);
    }

    /**
     * The product translation alias `pt` is joined on a single locale, so a plain comparison is
     * exact here; negative operators still have to accept products whose field is empty.
     * Comparisons ignore the case on every platform, as the MySQL collations do.
     */
    private function applyStringCondition(QueryBuilder $qb, string $field, FacetCondition $condition, string $param): void
    {
        $value = mb_strtolower($condition->getValue() ?? '');
        $lowered = "LOWER($field)";
        $like = sprintf("%s LIKE :%s ESCAPE '%s'", $lowered, $param, DqlExpressions::LIKE_ESCAPE);
        $notLike = sprintf("%s NOT LIKE :%s ESCAPE '%s'", $lowered, $param, DqlExpressions::LIKE_ESCAPE);

        match ($condition->getOperator()) {
            'equals' => $qb->andWhere("$lowered = :$param")->setParameter($param, $value),
            'not_equals' => $qb
                ->andWhere($qb->expr()->orX("$lowered != :$param", "$field IS NULL"))
                ->setParameter($param, $value),
            'contains' => $qb->andWhere($like)->setParameter($param, DqlExpressions::containsPattern($value)),
            'not_contains' => $qb
                ->andWhere($qb->expr()->orX($notLike, "$field IS NULL"))
                ->setParameter($param, DqlExpressions::containsPattern($value)),
            // isApplicable() only lets the operators of the type through.
            default => throw new \LogicException(sprintf('Unsupported operator "%s".', (string) $condition->getOperator())),
        };
    }

    private function applyAttributeCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        $referenceCode = (string) $condition->getReferenceCode();
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
                $referenceCode,
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

        $this->applyAttributeValueMatch($qb, $attributeValueAlias, $referenceCode, $condition->getOperator(), $value, $valueParam);
    }

    /**
     * Sylius stores attribute values in type specific fields. String comparisons target the
     * `text` field; select attributes keep the keys of their choices in the `json` field, which
     * are resolved in PHP against the keys and the translated labels of the choices (a LIKE on a
     * JSON column is not portable). Both ignore the case.
     *
     * This method only handles the positive operators used to detect a match; negations are
     * expressed by wrapping the same constraint into a `NOT EXISTS` subquery.
     */
    private function applyAttributeValueMatch(QueryBuilder $qb, string $alias, string $attributeCode, string $operator, string $value, string $param): void
    {
        $textMatch = match ($operator) {
            'equals' => "LOWER($alias.text) = :$param",
            'contains' => sprintf("LOWER(%s.text) LIKE :%s ESCAPE '%s'", $alias, $param, DqlExpressions::LIKE_ESCAPE),
            default => throw new \LogicException(sprintf('Unsupported operator "%s".', $operator)),
        };

        $needle = mb_strtolower($value);
        $qb->setParameter($param, $operator === 'contains' ? DqlExpressions::containsPattern($needle) : $needle);

        $selectValueIds = $this->selectAttributeValues->findMatchingValueIds(
            $attributeCode,
            static function (array $candidates) use ($operator, $needle): bool {
                foreach ($candidates as $candidate) {
                    $candidate = mb_strtolower($candidate);
                    if ($operator === 'equals' ? $candidate === $needle : str_contains($candidate, $needle)) {
                        return true;
                    }
                }

                return false;
            },
        );

        if ($selectValueIds === []) {
            $qb->andWhere($textMatch);

            return;
        }

        $qb->andWhere($qb->expr()->orX($textMatch, DqlExpressions::idIn("$alias.id", $selectValueIds)));
    }

    private function applyOptionCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        // isApplicable() only lets an existing reference through.
        $referenceCode = (string) $condition->getReferenceCode();

        $referenceParam = 'f_opt_code_' . $idx;
        $valueParam = 'f_opt_value_' . $idx;
        $value = mb_strtolower($condition->getValue() ?? '');

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
                ->andWhere('LOWER(sub_opt_translation_' . $idx . '.value) = :' . $valueParam)
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
            ->andWhere('LOWER(' . $translationAlias . '.value) = :' . $valueParam)
            ->setParameter($referenceParam, $referenceCode)
            ->setParameter($valueParam, $value);
    }

    private function applyTaxonCondition(QueryBuilder $qb, FacetCondition $condition, int $idx): void
    {
        // isApplicable() only lets an existing reference through.
        $referenceCode = (string) $condition->getReferenceCode();

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
}
