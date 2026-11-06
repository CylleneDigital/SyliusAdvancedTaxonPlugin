<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonTreePresenter;
use PHPUnit\Framework\TestCase;

final class TaxonTreePresenterTest extends TestCase
{
    /**
     * A limit no tree of this test reaches: the folding never applies with these.
     */
    private const OPEN_ALL = 1000;

    public function test_it_keeps_a_matching_taxon_with_its_ancestors_and_its_own_children(): void
    {
        self::assertSame(
            ['Men' => ['Clothes' => ['Shirts' => ['Formal shirts' => []]]]],
            $this->names($this->present($this->tree(), 'shirt')),
        );
    }

    public function test_it_compares_the_names_case_insensitively(): void
    {
        self::assertSame(
            ['Men' => ['Clothes' => ['Shirts' => ['Formal shirts' => []]]]],
            $this->names($this->present($this->tree(), 'FORMAL')),
        );
    }

    public function test_it_drops_the_branches_holding_no_match(): void
    {
        self::assertSame(
            ['Women' => ['Summer' => ['Dresses' => []]]],
            $this->names($this->present($this->tree(), 'summer')),
        );
    }

    public function test_it_keeps_a_matching_taxon_with_its_own_children_whole(): void
    {
        self::assertSame(
            ['Men' => ['Clothes' => ['Shirts' => ['Formal shirts' => []], 'Trousers' => []]]],
            $this->names($this->present($this->tree(), 'clothes')),
        );
    }

    public function test_it_returns_nothing_when_no_name_matches(): void
    {
        self::assertSame([], $this->present($this->tree(), 'watches'));
    }

    public function test_a_blank_term_keeps_the_whole_tree(): void
    {
        self::assertSame($this->names($this->tree()), $this->names($this->present($this->tree(), '   ')));
    }

    public function test_a_small_tree_is_opened_without_a_search(): void
    {
        self::assertSame(
            ['Men' => true, 'Clothes' => true, 'Shirts' => true, 'Women' => true, 'Summer' => true],
            $this->openness($this->present($this->tree(), '', 9)),
        );
    }

    public function test_a_tree_above_the_limit_is_folded_down_to_its_roots_without_a_search(): void
    {
        self::assertSame(
            ['Men' => false, 'Clothes' => false, 'Shirts' => false, 'Women' => false, 'Summer' => false],
            $this->openness($this->present($this->tree(), '', 7)),
        );
    }

    public function test_a_search_opens_the_branches_it_returns_above_the_limit(): void
    {
        self::assertSame(
            ['Men' => true, 'Clothes' => true, 'Shirts' => true],
            $this->openness($this->present($this->tree(), 'shirt', 1)),
        );
    }

    public function test_a_move_opens_the_branch_holding_the_moved_taxon(): void
    {
        // "Shirts" sits two levels down: the tree is folded, then opened down to it.
        self::assertSame(
            ['Men' => true, 'Clothes' => true, 'Shirts' => true, 'Women' => false, 'Summer' => false],
            $this->openness($this->present($this->tree(), '', 1, 3)),
        );
    }

    public function test_a_move_leaves_the_rest_of_the_tree_folded(): void
    {
        $presented = $this->present($this->tree(), '', 1, 3);

        self::assertSame(
            [
                'Men' => ['Clothes' => ['Shirts' => ['Formal shirts' => []], 'Trousers' => []]],
                'Women' => ['Summer' => ['Dresses' => []]],
            ],
            $this->names($presented),
            'A move neither filters nor hides a taxon: it only decides what is open.',
        );
        self::assertSame(['Men', 'Clothes', 'Shirts', 'Women', 'Summer'], array_keys($this->openness($presented)));
    }

    public function test_a_move_on_a_small_tree_leaves_the_whole_tree_open(): void
    {
        // Below the folding limit there is nothing to fold back: a move changes nothing to what is open.
        $presented = $this->present($this->tree(), '', self::OPEN_ALL, 3);

        self::assertSame(
            ['Men' => true, 'Clothes' => true, 'Shirts' => true, 'Women' => true, 'Summer' => true],
            $this->openness($presented),
        );
        self::assertSame($this->names($this->tree()), $this->names($presented));
    }

    public function test_a_move_of_an_unknown_taxon_falls_back_to_the_default(): void
    {
        self::assertSame(
            ['Men' => false, 'Clothes' => false, 'Shirts' => false, 'Women' => false, 'Summer' => false],
            $this->openness($this->present($this->tree(), '', 1, 999)),
        );
    }

    public function test_a_search_wins_over_a_move(): void
    {
        self::assertSame(
            ['Men' => true, 'Clothes' => true, 'Shirts' => true],
            $this->openness($this->present($this->tree(), 'shirt', 1, 10)),
        );
    }

    public function test_a_move_matches_a_taxon_id_returned_as_a_string(): void
    {
        $tree = [
            ['id' => '1', 'name' => 'Men', 'enabled' => true, 'children' => [
                ['id' => '3', 'name' => 'Shirts', 'enabled' => true, 'children' => []],
            ]],
        ];

        self::assertSame(['Men' => true], $this->openness($this->present($tree, '', 1, 3)));
    }

    public function test_it_leaves_a_tree_of_unexpected_nodes_alone(): void
    {
        $presenter = new TaxonTreePresenter();

        self::assertSame(['taxon'], $presenter->present(['taxon'], '', self::OPEN_ALL));
        self::assertSame([], $presenter->present(['taxon'], 'shirt', self::OPEN_ALL));
    }

    /**
     * @param array<array-key, mixed> $tree
     *
     * @return array<array-key, mixed>
     */
    private function present(array $tree, string $term, int $autoOpenLimit = self::OPEN_ALL, ?int $openTaxonId = null): array
    {
        return (new TaxonTreePresenter())->present($tree, $term, $autoOpenLimit, $openTaxonId);
    }

    /**
     * The taxon names, nested as the tree is: what the tree view lists.
     *
     * @param array<array-key, mixed> $nodes
     *
     * @return array<string, mixed>
     */
    private function names(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['name'] ?? null)) {
                continue;
            }

            $children = $node['children'] ?? [];
            $names[$node['name']] = is_array($children) ? $this->names($children) : [];
        }

        return $names;
    }

    /**
     * The open state sent with every taxon holding taxons, by taxon name.
     *
     * @param array<array-key, mixed> $nodes
     *
     * @return array<string, bool>
     */
    private function openness(array $nodes): array
    {
        $states = [];

        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['name'] ?? null)) {
                continue;
            }

            $children = $node['children'] ?? [];
            $state = $node['state'] ?? null;

            if (is_array($children) && [] !== $children) {
                self::assertIsArray($state);
                $states[$node['name']] = (bool) ($state['open'] ?? false);
            } else {
                self::assertNull($state, 'A taxon holding no taxon needs no open state.');
            }

            if (is_array($children)) {
                $states += $this->openness($children);
            }
        }

        return $states;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function tree(): array
    {
        return [
            $this->node(1, 'Men', [
                $this->node(2, 'Clothes', [
                    $this->node(3, 'Shirts', [$this->node(4, 'Formal shirts')]),
                    $this->node(5, 'Trousers'),
                ]),
            ]),
            $this->node(9, 'Women', [
                $this->node(10, 'Summer', [$this->node(11, 'Dresses')]),
            ]),
        ];
    }

    /**
     * @param list<array<array-key, mixed>> $children
     *
     * @return array<array-key, mixed>
     */
    private function node(int $id, string $name, array $children = []): array
    {
        return ['id' => $id, 'name' => $name, 'enabled' => true, 'children' => $children];
    }
}
