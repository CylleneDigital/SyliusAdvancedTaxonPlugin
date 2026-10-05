<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * A universe taxon renders a showcase page without a product list: a product whose main taxon is a
 * universe would get breadcrumbs and a canonical taxon pointing to a page that does not list it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotUniverseMainTaxon extends Constraint
{
    public string $message = 'cyllene_digital_sylius_advanced_taxon.product.main_taxon_universe';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
