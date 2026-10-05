<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

/**
 * Tells whether the attribute, option or taxon a facet condition refers to exists.
 */
interface FacetReferenceCheckerInterface
{
    public function exists(string $conditionType, string $referenceCode): bool;
}
