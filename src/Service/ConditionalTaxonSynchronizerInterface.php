<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;

/**
 * Materializes conditional taxons (facet driven taxons) as real product/taxon associations.
 */
interface ConditionalTaxonSynchronizerInterface
{
    /**
     * Synchronizes the product assignments of a single taxon: a conditional taxon gets exactly the
     * products matching its conditions, a taxon that is not conditional (any more) gets none.
     *
     * The synchronization is differential: products that stay assigned keep their
     * existing position, only obsolete associations are detached and only new
     * matches are attached.
     *
     * @return array{detached: int, attached: int, matched: int}
     */
    public function syncTaxon(AdvancedTaxonInterface $taxon): array;

    /**
     * Synchronizes every taxon flagged as conditional.
     *
     * @return array{taxons: int, detached: int, attached: int, matched: int}
     */
    public function syncAllConditionalTaxons(): array;
}
