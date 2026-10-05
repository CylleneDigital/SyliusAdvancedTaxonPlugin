<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

/**
 * DQL fragments shared by the condition and the storefront queries.
 */
final class DqlExpressions
{
    /**
     * Escape character of the LIKE patterns built from user input.
     */
    public const string LIKE_ESCAPE = '!';

    /**
     * The identifiers come from the database and are integers: they are inlined, so that a large
     * catalog never hits the bound parameters limit of the platform (65535 on PostgreSQL).
     *
     * @param list<int> $ids
     */
    public static function idIn(string $field, array $ids): string
    {
        return sprintf('%s IN (%s)', $field, implode(', ', array_map('intval', $ids)));
    }

    public static function containsPattern(string $value): string
    {
        $escape = self::LIKE_ESCAPE;

        return '%' . str_replace([$escape, '%', '_'], [$escape . $escape, $escape . '%', $escape . '_'], $value) . '%';
    }
}
