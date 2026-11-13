<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

/**
 * Shapes the taxon tree of the back office: filtered on the searched term, and folded by default on
 * a catalog large enough for the whole tree to be unreadable.
 *
 * A matching taxon keeps its branch whole: its ancestors stay, to show where it sits, and its own
 * descendants stay too, so that searching a taxon lists what it holds. A branch holding no match is
 * dropped. Names are compared case-insensitively, as the conditions do.
 *
 * The open state of each taxon is sent with the tree data, which the Sylius tree controller hands to
 * `infinite-tree`: it only reads it when its own `autoOpen` option is off, as the plugin template
 * sets it. That is what makes the folding follow the search without touching the Sylius controller.
 *
 * A tree folded down to its roots is unreadable after a move: the moved taxon is hidden again, and
 * its branch has to be reopened between each click. The branch holding the taxon a move just
 * touched is therefore opened, and the rest of the tree stays folded.
 */
final class TaxonTreePresenter
{
    /**
     * @param array<array-key, mixed> $tree          the tree built by the Sylius tree component
     * @param int                     $autoOpenLimit number of taxons above which a tree without a
     *                                               searched term is folded down to its roots
     * @param int|null                $openTaxonId   the taxon a move has just touched: the branch
     *                                               holding it is opened, the tree folded around it
     *
     * @return array<array-key, mixed>
     */
    public function present(array $tree, string $term, int $autoOpenLimit, ?int $openTaxonId = null): array
    {
        $term = trim($term);
        $searched = '' !== $term;
        $filtered = $searched ? $this->filter($tree, $term) : $tree;

        // A search opens the branches holding a match: what is found has to be visible straight away.
        if ($searched) {
            return $this->openNodes($filtered, true);
        }

        // Only a tree too large to read is folded; a small one stays as it always was, a move included.
        $open = $this->countNodes($tree) <= $autoOpenLimit;

        // A move re-renders a folded tree: opening the branch it touched keeps the moved taxon in
        // sight, where the folded tree would hide it again between each click.
        if (!$open && null !== $openTaxonId && $this->holds($filtered, $openTaxonId)) {
            return $this->openPath($this->openNodes($filtered, false), $openTaxonId);
        }

        return $this->openNodes($filtered, $open);
    }

    /**
     * @param array<array-key, mixed> $nodes
     *
     * @return list<array<array-key, mixed>>
     */
    private function filter(array $nodes, string $term): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if ($this->matches($node, $term)) {
                $kept[] = $node;

                continue;
            }

            $children = $node['children'] ?? null;

            if (!is_array($children)) {
                continue;
            }

            $matchingChildren = $this->filter($children, $term);

            if ([] === $matchingChildren) {
                continue;
            }

            $node['children'] = $matchingChildren;
            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * Sets the open state of every taxon holding taxons: `infinite-tree` then lists the tree folded
     * down to the taxons left open.
     *
     * @param array<array-key, mixed> $nodes
     *
     * @return array<array-key, mixed>
     */
    private function openNodes(array $nodes, bool $open): array
    {
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $children = $node['children'] ?? null;

            if (!is_array($children) || [] === $children) {
                continue;
            }

            $node['state'] = ['open' => $open];
            $node['children'] = $this->openNodes($children, $open);
            $nodes[$index] = $node;
        }

        return $nodes;
    }

    /**
     * Opens the taxons leading to the given one, and that taxon itself: `infinite-tree` then lists
     * the branch open down to it, the rest of the tree staying folded.
     *
     * @param array<array-key, mixed> $nodes
     *
     * @return array<array-key, mixed>
     */
    private function openPath(array $nodes, int $taxonId): array
    {
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $children = $node['children'] ?? null;

            if (!is_array($children) || [] === $children) {
                continue;
            }

            if (!$this->isTaxon($node, $taxonId) && !$this->holds($children, $taxonId)) {
                continue;
            }

            $node['state'] = ['open' => true];
            $node['children'] = $this->openPath($children, $taxonId);
            $nodes[$index] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<array-key, mixed> $nodes
     */
    private function holds(array $nodes, int $taxonId): bool
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if ($this->isTaxon($node, $taxonId)) {
                return true;
            }

            $children = $node['children'] ?? null;

            if (is_array($children) && $this->holds($children, $taxonId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The database drivers do not agree on the type of a taxon id: it is compared as a number.
     *
     * @param array<array-key, mixed> $node
     */
    private function isTaxon(array $node, int $taxonId): bool
    {
        $id = $node['id'] ?? null;

        return is_numeric($id) && (int) $id === $taxonId;
    }

    /**
     * @param array<array-key, mixed> $nodes
     */
    private function countNodes(array $nodes): int
    {
        $count = 0;

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            ++$count;

            $children = $node['children'] ?? null;

            if (is_array($children)) {
                $count += $this->countNodes($children);
            }
        }

        return $count;
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function matches(array $node, string $term): bool
    {
        $name = $node['name'] ?? null;

        return is_string($name) && false !== mb_stripos($name, $term);
    }
}
