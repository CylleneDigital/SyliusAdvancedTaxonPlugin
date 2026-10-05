<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

final readonly class ConditionalTaxonProductAssigner implements ConditionalTaxonSynchronizerInterface
{
    /**
     * Number of conditional taxons processed per flush/clear cycle during a full synchronization.
     */
    private const int BATCH_SIZE = 50;

    /**
     * Number of product links loaded at once when detaching.
     */
    private const int DETACH_CHUNK_SIZE = 1000;

    /**
     * @param TaxonRepositoryInterface<AdvancedTaxonInterface> $taxonRepository
     * @param FactoryInterface<ProductTaxonInterface> $productTaxonFactory
     * @param class-string<ProductTaxonInterface> $productTaxonClass
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConditionalTaxonQueryBuilder $conditionalTaxonQueryBuilder,
        private TaxonRepositoryInterface $taxonRepository,
        private FactoryInterface $productTaxonFactory,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private string $productTaxonClass,
        private string $productClass,
    ) {
    }

    /**
     * @return array{detached: int, attached: int, matched: int}
     */
    public function syncTaxon(AdvancedTaxonInterface $taxon): array
    {
        $locale = $this->translationLocaleProvider->getDefaultLocaleCode();

        if (!$taxon->isConditional()) {
            return [
                'detached' => $this->detachAll($taxon),
                'attached' => 0,
                'matched' => 0,
            ];
        }

        $currentAssignments = $this->getCurrentProductAssignments($taxon);
        $currentProductIds = array_keys($currentAssignments);

        $matchedProductIds = $taxon->getFacetConditions()->isEmpty()
            ? []
            : $this->findMatchingProductIds($taxon, $locale);

        $matchedLookup = array_fill_keys($matchedProductIds, true);
        $toDetach = [];
        foreach ($currentProductIds as $productId) {
            if (!isset($matchedLookup[$productId])) {
                $toDetach[] = (int) $productId;
            }
        }

        $currentLookup = array_fill_keys($currentProductIds, true);
        $toAttach = [];
        foreach ($matchedProductIds as $productId) {
            if (!isset($currentLookup[$productId])) {
                $toAttach[] = (int) $productId;
            }
        }

        $detached = $this->detachProductTaxons($taxon, $toDetach);
        $nextPosition = $this->getNextPosition($currentAssignments, $toDetach);
        $attached = $this->attachProductsToTaxon($taxon, $toAttach, $nextPosition);

        return [
            'detached' => $detached,
            'attached' => $attached,
            'matched' => count($matchedProductIds),
        ];
    }

    /**
     * Processes taxons by batches and flushes/clears the entity manager between them so that
     * full catalog synchronizations stay memory bounded. The caller does not need to flush.
     *
     * @return array{taxons: int, detached: int, attached: int, matched: int}
     */
    public function syncAllConditionalTaxons(): array
    {
        $summary = [
            'taxons' => 0,
            'detached' => 0,
            'attached' => 0,
            'matched' => 0,
        ];

        $lastId = 0;

        while (true) {
            // Keyset pagination: the entity manager is cleared between two batches.
            /** @var list<AdvancedTaxonInterface> $taxons */
            $taxons = $this->entityManager->createQueryBuilder()
                ->select('taxon')
                ->from($this->taxonRepository->getClassName(), 'taxon')
                ->andWhere('taxon.conditional = true')
                ->andWhere('taxon.id > :last_id')
                ->setParameter('last_id', $lastId)
                ->orderBy('taxon.id')
                ->setMaxResults(self::BATCH_SIZE)
                ->getQuery()
                ->getResult();

            if ($taxons === []) {
                break;
            }

            foreach ($taxons as $taxon) {
                ++$summary['taxons'];
                $result = $this->syncTaxon($taxon);
                $summary['detached'] += $result['detached'];
                $summary['attached'] += $result['attached'];
                $summary['matched'] += $result['matched'];
                $id = $taxon->getId();
                if (is_int($id)) {
                    $lastId = $id;
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        return $summary;
    }

    /**
     * The assignments of a taxon that stopped being conditional were materialized from conditions
     * it no longer has.
     */
    private function detachAll(AdvancedTaxonInterface $taxon): int
    {
        return $this->detachProductTaxons($taxon, array_keys($this->getCurrentProductAssignments($taxon)));
    }

    /**
     * @return array<int, int>
     */
    private function getCurrentProductAssignments(AdvancedTaxonInterface $taxon): array
    {
        if ($taxon->getId() === null) {
            return [];
        }

        /** @var array<int, array{product_id: int|string, position: int|string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(pt.product) AS product_id', 'pt.position AS position')
            ->from($this->productTaxonClass, 'pt')
            ->andWhere('pt.taxon = :taxon')
            ->setParameter('taxon', $taxon)
            ->getQuery()
            ->getArrayResult();

        $assignments = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            if ($productId <= 0) {
                continue;
            }

            $assignments[$productId] = (int) ($row['position'] ?? 0);
        }

        return $assignments;
    }

    /**
     * Removes the product/taxon links through the unit of work, so that Doctrine events fire (search
     * indexers, ...) and the product collections already in memory stay consistent.
     *
     * @param array<int, int> $productIds
     */
    private function detachProductTaxons(AdvancedTaxonInterface $taxon, array $productIds): int
    {
        $detached = 0;

        foreach (array_chunk($productIds, self::DETACH_CHUNK_SIZE) as $chunk) {
            /** @var list<ProductTaxonInterface> $productTaxons */
            $productTaxons = $this->entityManager->createQueryBuilder()
                ->select('pt')
                ->from($this->productTaxonClass, 'pt')
                ->andWhere('pt.taxon = :taxon')
                ->andWhere(sprintf('IDENTITY(pt.product) IN (%s)', implode(', ', array_map('intval', $chunk))))
                ->setParameter('taxon', $taxon)
                ->getQuery()
                ->getResult();

            foreach ($productTaxons as $productTaxon) {
                // Only a product already in memory needs its collection kept consistent: loading the
                // others would cost two queries per detached product.
                $product = $productTaxon->getProduct();
                if ($product !== null && !$this->entityManager->getUnitOfWork()->isUninitializedObject($product)) {
                    $productTaxonsOfProduct = $product->getProductTaxons();
                    if (!$productTaxonsOfProduct instanceof PersistentCollection || $productTaxonsOfProduct->isInitialized()) {
                        $product->removeProductTaxon($productTaxon);
                    }
                }
                $this->entityManager->remove($productTaxon);
                ++$detached;
            }
        }

        return $detached;
    }

    /**
     * @param array<int, int> $assignments
     * @param array<int, int> $detachedProductIds
     */
    private function getNextPosition(array $assignments, array $detachedProductIds): int
    {
        if ($assignments === []) {
            return 0;
        }

        $detachedLookup = array_fill_keys($detachedProductIds, true);
        $maxPosition = -1;

        foreach ($assignments as $productId => $position) {
            if (isset($detachedLookup[$productId])) {
                continue;
            }

            $maxPosition = max($maxPosition, (int) $position);
        }

        return $maxPosition + 1;
    }

    /**
     * @return array<int, int>
     */
    private function findMatchingProductIds(AdvancedTaxonInterface $taxon, string $localeCode): array
    {
        $qb = $this->conditionalTaxonQueryBuilder->buildQuery($taxon, $localeCode);
        $qb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('DISTINCT p.id AS id');

        /** @var array<int, array{id: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param array<int, int> $productIds
     */
    private function attachProductsToTaxon(AdvancedTaxonInterface $taxon, array $productIds, int $startPosition): int
    {
        if ($productIds === []) {
            return 0;
        }

        $attached = 0;
        $position = $startPosition;

        foreach ($productIds as $productId) {
            $productTaxon = $this->productTaxonFactory->createNew();

            $productTaxon->setProduct($this->entityManager->getReference($this->productClass, $productId));
            $productTaxon->setTaxon($taxon);
            $productTaxon->setPosition($position);

            $this->entityManager->persist($productTaxon);
            ++$attached;
            ++$position;
        }

        return $attached;
    }
}
