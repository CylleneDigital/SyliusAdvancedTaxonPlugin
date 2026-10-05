<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Select attribute values are stored as choice keys in a JSON column, which cannot be searched in a
 * portable way: their choices are matched in PHP, and the queries filter on the matching value ids.
 */
final class SelectAttributeValueResolver implements ResetInterface
{
    /**
     * Select attribute values by attribute code, loaded once per request: a page applies the same
     * filters to the grid and to every facet query.
     *
     * @var array<string, list<array{id: int, keys: list<string>}>>
     */
    private array $values = [];

    /**
     * Choice labels by attribute code, read once: the configuration holds every choice in every locale.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private array $labels = [];

    /**
     * @param class-string $attributeValueClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $attributeValueClass,
    ) {
    }

    /**
     * Returns the identifiers of the select attribute values of an attribute whose choices match.
     *
     * @param callable(list<string>): bool $matches receives the keys and the labels of one choice
     *
     * @return list<int>
     */
    public function findMatchingValueIds(string $attributeCode, callable $matches): array
    {
        $labels = $this->choiceLabelsOf($attributeCode);
        $ids = [];
        foreach ($this->fetchSelectAttributeValues($attributeCode) as $row) {
            foreach ($row['keys'] as $key) {
                if ($matches([$key, ...array_values($labels[$key] ?? [])])) {
                    $ids[] = $row['id'];

                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array<string, array<string, string>> labels by locale, by choice key
     */
    private static function choiceLabels(mixed $configuration): array
    {
        $choices = is_array($configuration) && is_array($configuration['choices'] ?? null) ? $configuration['choices'] : [];

        $labels = [];
        foreach ($choices as $key => $translations) {
            if (!is_array($translations)) {
                continue;
            }

            foreach ($translations as $locale => $label) {
                if (is_scalar($label)) {
                    $labels[(string) $key][(string) $locale] = (string) $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @return array<string, array<string, string>> labels by locale, by choice key
     */
    public function choiceLabelsOf(string $attributeCode): array
    {
        if (!isset($this->labels[$attributeCode])) {
            /** @var list<array{configuration: mixed}> $rows */
            $rows = $this->entityManager->createQueryBuilder()
                ->select('a.configuration AS configuration')
                ->from($this->attributeValueClass, 'v')
                ->join('v.attribute', 'a')
                ->andWhere('a.code = :code')
                ->setParameter('code', $attributeCode)
                ->setMaxResults(1)
                ->getQuery()
                ->getArrayResult();

            $this->labels[$attributeCode] = self::choiceLabels($rows[0]['configuration'] ?? null);
        }

        return $this->labels[$attributeCode];
    }

    #[\Override]
    public function reset(): void
    {
        $this->values = [];
        $this->labels = [];
    }

    /**
     * @return list<array{id: int, keys: list<string>}>
     */
    private function fetchSelectAttributeValues(string $attributeCode): array
    {
        if (isset($this->values[$attributeCode])) {
            return $this->values[$attributeCode];
        }

        /** @var list<array{id: int|string, json: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('v.id AS id', 'v.json AS json')
            ->from($this->attributeValueClass, 'v')
            ->join('v.attribute', 'a')
            ->andWhere('a.code = :code')
            ->andWhere('v.json IS NOT NULL')
            ->setParameter('code', $attributeCode)
            ->getQuery()
            ->getArrayResult();

        $values = [];
        foreach ($rows as $row) {
            $keys = array_values(array_filter(is_array($row['json']) ? $row['json'] : [], 'is_string'));
            if ($keys === []) {
                continue;
            }

            $values[] = ['id' => (int) $row['id'], 'keys' => $keys];
        }

        return $this->values[$attributeCode] = $values;
    }
}
