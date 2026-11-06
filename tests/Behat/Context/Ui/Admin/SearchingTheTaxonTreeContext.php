<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Taxon\TaxonTreeElement;

/**
 * Searches the taxon tree of the admin taxon page. The page itself is opened by the Sylius steps
 * ("I want to modify the ... taxon").
 */
final class SearchingTheTaxonTreeContext implements Context
{
    public function __construct(private readonly TaxonTreeElement $taxonTree)
    {
    }

    #[When('I search :term in the taxon tree')]
    public function iSearchInTheTaxonTree(string $term): void
    {
        $this->taxonTree->search($term);
    }

    #[When('I clear the taxon tree search')]
    public function iClearTheTaxonTreeSearch(): void
    {
        $this->taxonTree->search('');
    }

    #[Then('the taxon tree should list :taxon only')]
    public function theTaxonTreeShouldListOnlyOne(string $taxon): void
    {
        $this->taxonTree->waitUntilListedTaxonsAre($taxon);
    }

    #[Then('the taxon tree should list :firstTaxon and :secondTaxon only')]
    public function theTaxonTreeShouldListOnly(string $firstTaxon, string $secondTaxon): void
    {
        $this->taxonTree->waitUntilListedTaxonsAre($firstTaxon, $secondTaxon);
    }

    #[Then('the taxon tree should list :firstTaxon, :secondTaxon and :thirdTaxon')]
    public function theTaxonTreeShouldList(string $firstTaxon, string $secondTaxon, string $thirdTaxon): void
    {
        $this->taxonTree->waitUntilListedTaxonsContain($firstTaxon, $secondTaxon, $thirdTaxon);
    }

    #[Then('the taxon tree should say that no taxon matches :term')]
    public function theTaxonTreeShouldSayThatNoTaxonMatches(string $term): void
    {
        $this->taxonTree->waitUntilNoTaxonMatches($term);
    }
}
