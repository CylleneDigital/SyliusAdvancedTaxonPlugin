<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Message;

/**
 * Asks for the product assignments of a conditional taxon to be materialized again, or detached
 * when the taxon stopped being conditional.
 *
 * Handled synchronously unless the application routes it to an asynchronous transport.
 */
final readonly class SynchronizeConditionalTaxon
{
    public function __construct(
        public int $taxonId,
    ) {
    }
}
