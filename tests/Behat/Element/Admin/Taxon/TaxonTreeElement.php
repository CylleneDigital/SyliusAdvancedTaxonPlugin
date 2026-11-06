<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Taxon;

use Behat\Mink\Element\NodeElement;
use FriendsOfBehat\PageObjectExtension\Element\Element;
use Webmozart\Assert\Assert;

/**
 * The search field above the taxon tree, and the taxons the tree lists under it. The rows are the
 * ones the Sylius controller renders: the tree only holds the ones on screen.
 */
final class TaxonTreeElement extends Element
{
    private const BROWSER_TIMEOUT_MS = 5000;

    private const LISTED_TAXONS = 'Array.from(document.querySelectorAll("[data-test-tree-taxons] [data-test-tree-taxon]")).map(function (row) { return row.getAttribute("data-test-tree-taxon"); })';

    private const NO_RESULT = 'document.querySelector("[data-test-taxon-tree-no-result]")';

    public function search(string $term): void
    {
        $this->waitUntil('document.querySelector("[data-test-taxon-tree-search]") !== null', 'The taxon tree search field did not show up.');

        $field = $this->getDocument()->find('css', '[data-test-taxon-tree-search]');
        Assert::isInstanceOf($field, NodeElement::class, 'The taxon tree search field could not be found.');

        $field->setValue($term);
    }

    /**
     * Waits for the tree to be left with exactly these taxons, in their order.
     */
    public function waitUntilListedTaxonsAre(string ...$taxons): void
    {
        $this->waitUntil(
            sprintf('%s.join("\n") === %s', self::LISTED_TAXONS, json_encode(implode("\n", array_values($taxons)))),
            sprintf('The taxon tree lists %s instead of %s.', json_encode($this->getListedTaxons()), json_encode(array_values($taxons))),
        );
    }

    /**
     * Waits for these taxons to be listed, whatever else the tree lists.
     */
    public function waitUntilListedTaxonsContain(string ...$taxons): void
    {
        foreach ($taxons as $taxon) {
            $this->waitUntil(
                sprintf('%s.indexOf(%s) !== -1', self::LISTED_TAXONS, json_encode($taxon)),
                sprintf('The taxon tree does not list "%s" but %s.', $taxon, json_encode($this->getListedTaxons())),
            );
        }
    }

    public function waitUntilNoTaxonMatches(string $term): void
    {
        $expected = sprintf('No taxon name matches "%s".', $term);

        $this->waitUntil(
            sprintf('%s !== null && %1$s.textContent.trim() === %s', self::NO_RESULT, json_encode($expected)),
            sprintf('The taxon tree does not say "%s".', $expected),
        );
    }

    /**
     * @return list<string> the taxons the tree lists right now
     */
    private function getListedTaxons(): array
    {
        $listed = $this->getSession()->evaluateScript(self::LISTED_TAXONS);
        Assert::isArray($listed);

        $taxons = [];
        foreach ($listed as $taxon) {
            Assert::string($taxon);
            $taxons[] = $taxon;
        }

        return $taxons;
    }

    private function waitUntil(string $condition, string $failure): void
    {
        Assert::true($this->getSession()->wait(self::BROWSER_TIMEOUT_MS, $condition), $failure);
    }
}
