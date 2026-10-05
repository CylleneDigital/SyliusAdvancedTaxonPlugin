<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConditionalTaxonProductAssigner implements ConditionalTaxonSynchronizerInterface
{
    /**
     * Number of conditional taxons processed per flush/clear cycle during a full synchronization.
     */
    private const BATCH_SIZE = 50;

    /**
     * @param TaxonRepositoryInterface<Taxon> $taxonRepository
     * @param class-string<ProductTaxonInterface> $productTaxonClass
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FacetProductQueryBuilder $facetProductQueryBuilder,
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly TaxonRepositoryInterface $taxonRepository,
        #[Autowire(service: 'sylius.factory.product_taxon')]
        private readonly FactoryInterface $productTaxonFactory,
        #[Autowire(service: 'sylius.translation_locale_provider')]
        private readonly TranslationLocaleProviderInterface $translationLocaleProvider,
        #[Autowire('%sylius.model.product_taxon.class%')]
        private readonly string $productTaxonClass,
        #[Autowire('%sylius.model.product.class%')]
        private readonly string $productClass,
    ) {
    }

    /**
     * @return array{detached: int, attached: int, matched: int}
     */
    public function syncTaxon(Taxon $taxon, ?string $localeCode = null): array
    {
        $locale = $localeCode ?: $this->translationLocaleProvider->getDefaultLocaleCode();

        if (!$taxon->isConditional()) {
            return [
                'detached' => 0,
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
     * full catalogue synchronizations stay memory bounded. The caller does not need to flush.
     *
     * @return array{taxons: int, detached: int, attached: int, matched: int}
     */
    public function syncAllConditionalTaxons(?string $localeCode = null): array
    {
        $locale = $localeCode ?: $this->translationLocaleProvider->getDefaultLocaleCode();

        $summary = [
            'taxons' => 0,
            'detached' => 0,
            'attached' => 0,
            'matched' => 0,
        ];

        $offset = 0;

        while (true) {
            /** @var array<int, Taxon> $taxons */
            $taxons = $this->taxonRepository->findBy(['conditional' => true], [], self::BATCH_SIZE, $offset);

            if ($taxons === []) {
                break;
            }

            foreach ($taxons as $taxon) {
                ++$summary['taxons'];
                $result = $this->syncTaxon($taxon, $locale);
                $summary['detached'] += $result['detached'];
                $summary['attached'] += $result['attached'];
                $summary['matched'] += $result['matched'];
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $offset += self::BATCH_SIZE;
        }

        return $summary;
    }

    /**
     * @return array<int, int>
     */
    private function getCurrentProductAssignments(Taxon $taxon): array
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
     * @param array<int, int> $productIds
     */
    private function detachProductTaxons(Taxon $taxon, array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $deleted = $this->entityManager->createQueryBuilder()
            ->delete($this->productTaxonClass, 'pt')
            ->andWhere('pt.taxon = :taxon')
            ->andWhere('IDENTITY(pt.product) IN (:productIds)')
            ->setParameter('taxon', $taxon)
            ->setParameter('productIds', $productIds)
            ->getQuery()
            ->execute();

        return is_numeric($deleted) ? (int) $deleted : 0;
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
    private function findMatchingProductIds(Taxon $taxon, string $localeCode): array
    {
        $qb = $this->facetProductQueryBuilder->buildQuery($taxon, $localeCode);
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
    private function attachProductsToTaxon(Taxon $taxon, array $productIds, int $startPosition): int
    {
        if ($productIds === []) {
            return 0;
        }

        $attached = 0;
        $position = $startPosition;

        foreach ($productIds as $productId) {
            $productTaxon = $this->productTaxonFactory->createNew();

            if (!$productTaxon instanceof ProductTaxonInterface) {
                throw new LogicException(sprintf('The product taxon factory must create instances of "%s", "%s" given.', ProductTaxonInterface::class, get_debug_type($productTaxon)));
            }

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
