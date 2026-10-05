<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Asks for a conditional taxon to be materialized once its conditions have really changed in the
 * database, whichever entry point persisted them (admin form, API, fixtures, console commands).
 *
 * Candidates are collected during `onFlush`, among the taxons whose conditional flag changed and
 * the taxons whose conditions were added, edited or removed: saving a taxon for any other reason
 * (renaming, moving it in the tree) does not trigger a synchronization. Their identifiers are read
 * in `postFlush`, once they exist.
 *
 * The messages are only dispatched once the flush is over: at the end of the request, of the command
 * or of the handled message. A synchronous handler flushes, and Doctrine does not support a flush
 * inside `postFlush`: the collection deletions of the outer flush, not cleaned up yet, would run
 * again without their insertions.
 */
final class ConditionalTaxonSyncListener implements ResetInterface
{
    /** @var array<int, AdvancedTaxonInterface> */
    private array $pendingTaxons = [];

    /**
     * Taxons that stopped being conditional: their materialized products are detached.
     *
     * @var array<int, true>
     */
    private array $turnedOffTaxons = [];

    /**
     * Identifiers of the taxons to synchronize, read once the flush assigned them.
     *
     * @var array<int, true>
     */
    private array $taxonIdsToSynchronize = [];

    /**
     * Guards against the re-entrance caused by the flush performed while synchronizing.
     */
    private bool $isDispatching = false;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->isDispatching) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof AdvancedTaxonInterface && $entity->isConditional()) {
                $this->collect($entity);
            }

            $this->collectConditionTaxon($entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof AdvancedTaxonInterface && $this->hasChanged($unitOfWork, $entity, 'conditional')) {
                $this->collect($entity);

                if (!$entity->isConditional()) {
                    $this->turnedOffTaxons[spl_object_id($entity)] = true;
                }
            }

            $this->collectConditionTaxon($entity);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof AdvancedTaxonInterface) {
                $this->collectConditionTaxon($entity);
            }
        }

        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $this->collectFromCollection($collection);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isDispatching || $this->pendingTaxons === []) {
            return;
        }

        $objectManager = $args->getObjectManager();
        foreach ($this->pendingTaxons as $objectId => $taxon) {
            $taxonId = $taxon->getId();
            if (!is_int($taxonId) || !$objectManager->contains($taxon)) {
                continue;
            }

            // A taxon that is not conditional is only synchronized when it just stopped being one:
            // its products are then detached. A regular taxon keeps its manual products.
            if ($taxon->isConditional() || isset($this->turnedOffTaxons[$objectId])) {
                $this->taxonIdsToSynchronize[$taxonId] = true;
            }
        }

        $this->pendingTaxons = [];
        $this->turnedOffTaxons = [];
    }

    /**
     * Called at the end of the request (`kernel.response`), of a console command
     * (`console.terminate`) and of a message handled by a worker.
     */
    public function dispatchPending(): void
    {
        if ($this->isDispatching || $this->taxonIdsToSynchronize === []) {
            return;
        }

        $taxonIds = array_keys($this->taxonIdsToSynchronize);
        $this->taxonIdsToSynchronize = [];
        $this->isDispatching = true;

        try {
            foreach ($taxonIds as $taxonId) {
                $this->messageBus->dispatch(new SynchronizeConditionalTaxon($taxonId));
            }
        } finally {
            $this->isDispatching = false;
        }
    }

    public function reset(): void
    {
        $this->pendingTaxons = [];
        $this->turnedOffTaxons = [];
        $this->taxonIdsToSynchronize = [];
    }

    private function hasChanged(UnitOfWork $unitOfWork, object $entity, string $field): bool
    {
        return array_key_exists($field, $unitOfWork->getEntityChangeSet($entity));
    }

    private function collectConditionTaxon(object $entity): void
    {
        if ($entity instanceof FacetCondition && $entity->getTaxon() !== null) {
            $this->collect($entity->getTaxon());
        }
    }

    private function collect(AdvancedTaxonInterface $taxon): void
    {
        $this->pendingTaxons[spl_object_id($taxon)] = $taxon;
    }

    /**
     * @param PersistentCollection<array-key, object> $collection
     */
    private function collectFromCollection(PersistentCollection $collection): void
    {
        $owner = $collection->getOwner();

        if ($owner instanceof AdvancedTaxonInterface && $owner->getFacetConditions() === $collection) {
            $this->collect($owner);
        }
    }
}
