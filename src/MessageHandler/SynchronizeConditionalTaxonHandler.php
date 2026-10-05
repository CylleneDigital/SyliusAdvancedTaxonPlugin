<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\MessageHandler;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonSynchronizerInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final readonly class SynchronizeConditionalTaxonHandler
{
    /**
     * @param TaxonRepositoryInterface<AdvancedTaxonInterface> $taxonRepository
     */
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
        private ConditionalTaxonSynchronizerInterface $synchronizer,
    ) {
    }

    public function __invoke(SynchronizeConditionalTaxon $message): void
    {
        $taxon = $this->taxonRepository->find($message->taxonId);

        // The taxon may have been deleted since the message was dispatched. A taxon that is not
        // conditional any more gets its materialized products detached by the synchronization.
        if (!$taxon instanceof AdvancedTaxonInterface) {
            return;
        }

        // The doctrine_transaction middleware of sylius.command_bus flushes the assignments.
        $this->synchronizer->syncTaxon($taxon);
    }
}
