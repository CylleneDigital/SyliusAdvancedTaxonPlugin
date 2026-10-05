<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Removes from the storage the uploaded pictogram a taxon no longer uses (taxon deleted, icon
 * replaced), once the change is flushed: a failed flush leaves the file in place.
 */
final class TaxonIconRemovalListener implements ResetInterface
{
    /**
     * Pending files per entity manager: a failed flush closes its manager, and the files it had
     * scheduled must not be removed by the next flush of the manager that replaces it.
     *
     * @var \WeakMap<EntityManagerInterface, list<string>>
     */
    private \WeakMap $pendingIcons;

    public function __construct(
        private readonly TaxonIconUploader $taxonIconUploader,
    ) {
        $this->pendingIcons = new \WeakMap();
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if ($args->getObject() instanceof AdvancedTaxonInterface && $args->hasChangedField('icon')) {
            $this->schedule($args->getObjectManager(), $args->getOldValue('icon'));
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $taxon = $args->getObject();
        if ($taxon instanceof AdvancedTaxonInterface) {
            $this->schedule($args->getObjectManager(), $taxon->getIcon());
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $icons = $this->pendingIcons[$entityManager] ?? [];
        unset($this->pendingIcons[$entityManager]);

        foreach ($icons as $icon) {
            $this->taxonIconUploader->remove($icon);
        }
    }

    private function schedule(EntityManagerInterface $entityManager, mixed $icon): void
    {
        if (is_string($icon) && TaxonIconUploader::storagePath($icon) !== null) {
            $this->pendingIcons[$entityManager] = [...$this->pendingIcons[$entityManager] ?? [], $icon];
        }
    }

    public function reset(): void
    {
        $this->pendingIcons = new \WeakMap();
    }
}
