<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonSynchronizerInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;

/**
 * Materializes conditional taxons once they have really been inserted or updated.
 *
 * The synchronization used to be triggered by the admin taxon form. It therefore also ran for
 * invalid submissions, and never ran when a taxon was persisted outside of that form (API calls,
 * fixtures, custom console commands).
 *
 * Candidates are collected during `onFlush` and processed in `postFlush`, which guarantees that:
 * - only taxons that actually reached the database are synchronized;
 * - identifiers exist, so the differential sync can read the current product assignments;
 * - every taxon collected during the same unit of work is handled with a single extra flush.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ConditionalTaxonSyncListener
{
    /** @var array<int, Taxon> */
    private array $pendingTaxons = [];

    /**
     * Guards against the re-entrance caused by the flush performed while synchronizing.
     */
    private bool $isSynchronizing = false;

    public function __construct(
        private readonly ConditionalTaxonSynchronizerInterface $synchronizer,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->isSynchronizing) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity);
        }

        // Editing only the facet conditions of an already conditional taxon leaves the taxon
        // itself unchanged: the dirty collection is the only signal available.
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $this->collectFromCollection($collection);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isSynchronizing || $this->pendingTaxons === []) {
            return;
        }

        $pendingTaxons = $this->pendingTaxons;
        $this->pendingTaxons = [];

        $objectManager = $args->getObjectManager();
        $this->isSynchronizing = true;

        try {
            $hasChanges = false;

            foreach ($pendingTaxons as $taxon) {
                if (null === $taxon->getId() || !$objectManager->contains($taxon) || !$taxon->isConditional()) {
                    continue;
                }

                $result = $this->synchronizer->syncTaxon($taxon);
                $hasChanges = $hasChanges || $result['attached'] > 0 || $result['detached'] > 0;
            }

            if ($hasChanges) {
                $objectManager->flush();
            }
        } finally {
            $this->isSynchronizing = false;
        }
    }

    private function collect(object $entity): void
    {
        if ($entity instanceof Taxon) {
            $this->pendingTaxons[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * @param PersistentCollection<array-key, object> $collection
     */
    private function collectFromCollection(PersistentCollection $collection): void
    {
        $mapping = $collection->getMapping();

        if (($mapping['fieldName'] ?? null) !== 'facetConditions') {
            return;
        }

        $owner = $collection->getOwner();

        if ($owner !== null) {
            $this->collect($owner);
        }
    }
}
