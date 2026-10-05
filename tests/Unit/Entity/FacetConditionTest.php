<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacetConditionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function operatorProvider(): iterable
    {
        yield 'equals is positive' => ['equals', false];
        yield 'contains is positive' => ['contains', false];
        yield 'in is positive' => ['in', false];
        yield 'is_in_stock is positive' => ['is_in_stock', false];
        yield 'not_equals is negative' => ['not_equals', true];
        yield 'not_contains is negative' => ['not_contains', true];
        yield 'not_in is negative' => ['not_in', true];
    }

    #[DataProvider('operatorProvider')]
    public function test_it_detects_negative_operators(string $operator, bool $expected): void
    {
        self::assertSame($expected, FacetCondition::isNegativeOperator($operator));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function negatedOperatorProvider(): iterable
    {
        yield 'not_equals' => ['not_equals', 'equals'];
        yield 'not_contains' => ['not_contains', 'contains'];
        yield 'not_in' => ['not_in', 'in'];
        yield 'unknown operator is returned unchanged' => ['is_in_stock', 'is_in_stock'];
    }

    #[DataProvider('negatedOperatorProvider')]
    public function test_it_returns_the_positive_counterpart_of_an_operator(string $operator, string $expected): void
    {
        self::assertSame($expected, FacetCondition::negatedOperator($operator));
    }

    /**
     * Every declared operator must be classifiable as positive or negative, and its negation must
     * be symmetrical so that NOT EXISTS subqueries stay consistent.
     */
    public function test_operator_negation_is_symmetrical_for_every_declared_operator(): void
    {
        foreach (FacetCondition::OPERATORS_BY_TYPE as $type => $operators) {
            foreach ($operators as $operator) {
                $negated = FacetCondition::negatedOperator($operator);

                if ($negated === $operator) {
                    self::assertFalse(
                        FacetCondition::isNegativeOperator($operator),
                        sprintf('Operator "%s" of type "%s" is neither positive nor negative.', $operator, $type),
                    );

                    continue;
                }

                self::assertTrue(
                    FacetCondition::isNegativeOperator($operator),
                    sprintf('Operator "%s" of type "%s" should be flagged as negative.', $operator, $type),
                );
                self::assertFalse(
                    FacetCondition::isNegativeOperator($negated),
                    sprintf('Negated operator "%s" must be positive.', $negated),
                );
                self::assertContains(
                    $negated,
                    $operators,
                    sprintf('Negated operator "%s" is not declared for type "%s".', $negated, $type),
                );
            }
        }
    }
}
