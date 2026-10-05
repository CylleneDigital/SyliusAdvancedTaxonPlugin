<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

/**
 * Materializes conditional taxons (facet driven taxons) as real product/taxon associations.
 */
interface ConditionalTaxonSynchronizerInterface
{
    /**
     * Synchronizes the product assignments of a single conditional taxon.
     *
     * The synchronization is differential: products that stay assigned keep their
     * existing position, only obsolete associations are detached and only new
     * matches are attached.
     *
     * @return array{detached: int, attached: int, matched: int}
     */
    public function syncTaxon(Taxon $taxon, ?string $localeCode = null): array;

    /**
     * Synchronizes every taxon flagged as conditional.
     *
     * @return array{taxons: int, detached: int, attached: int, matched: int}
     */
    public function syncAllConditionalTaxons(?string $localeCode = null): array;
}
