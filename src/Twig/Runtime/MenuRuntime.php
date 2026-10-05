<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class MenuRuntime implements RuntimeExtensionInterface
{
    /**
     * Depth of the subtree preloaded under the first level of the menu.
     */
    private const int LEVELS = 3;

    /**
     * @param class-string $taxonClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $taxonClass,
    ) {
    }

    /**
     * Loads, in a few grouped queries, what the menu reads on the subtree it renders (children and
     * their translations, featured children, media), instead of one query per taxon and collection
     * on every shop page.
     *
     * @param iterable<TaxonInterface> $taxons first level of the menu
     */
    public function preloadMenu(iterable $taxons): void
    {
        $roots = [];
        $firstLevel = null;
        foreach ($taxons as $taxon) {
            if ($taxon instanceof AdvancedTaxonInterface && $taxon->getId() !== null && $taxon->getRoot() !== null) {
                $roots[spl_object_id($taxon->getRoot())] = $taxon->getRoot();
                $firstLevel = min($firstLevel ?? \PHP_INT_MAX, (int) $taxon->getLevel());
            }
        }

        if ($roots === [] || $firstLevel === null) {
            return;
        }

        /** @var list<TaxonInterface> $subtree */
        $subtree = $this->entityManager->createQueryBuilder()
            ->select('taxon', 'child', 'translation')
            ->from($this->taxonClass, 'taxon')
            ->leftJoin('taxon.children', 'child')
            ->leftJoin('child.translations', 'translation')
            ->andWhere('taxon.root IN (:roots)')
            ->andWhere('taxon.level BETWEEN :first_level AND :last_level')
            ->setParameter('roots', array_values($roots))
            ->setParameter('first_level', $firstLevel)
            ->setParameter('last_level', $firstLevel + self::LEVELS - 1)
            ->getQuery()
            ->getResult();

        $this->fetch('featuredChildren', $subtree);
        $this->fetch('images', $subtree);
    }

    /**
     * Returns the enabled children of a taxon, with the featured ones moved to the front
     * while keeping the configured order and removing duplicates.
     *
     * @return array<int, TaxonInterface>
     */
    public function getOrderedChildren(TaxonInterface $taxon): array
    {
        $enabledChildren = [];
        foreach ($taxon->getEnabledChildren() as $enabledChild) {
            $enabledChildren[$this->getTaxonKey($enabledChild)] = $enabledChild;
        }

        $orderedChildren = [];
        $seenKeys = [];

        $featuredChildren = $taxon instanceof AdvancedTaxonInterface ? $taxon->getFeaturedChildren() : [];
        foreach ($featuredChildren as $featuredChild) {
            $key = $this->getTaxonKey($featuredChild);

            if (!isset($enabledChildren[$key]) || isset($seenKeys[$key])) {
                continue;
            }

            $orderedChildren[] = $featuredChild;
            $seenKeys[$key] = true;
        }

        foreach ($enabledChildren as $key => $enabledChild) {
            if (isset($seenKeys[$key])) {
                continue;
            }

            $orderedChildren[] = $enabledChild;
            $seenKeys[$key] = true;
        }

        return $orderedChildren;
    }

    /**
     * Loads, in a few grouped queries, what a universe page reads on its children (translations,
     * featured texts and products, media, grandchildren) instead of several queries per child.
     */
    public function preloadUniverse(TaxonInterface $taxon): void
    {
        if ($taxon->getId() === null) {
            return;
        }

        $this->entityManager->createQueryBuilder()
            ->select('taxon', 'child', 'translation')
            ->from($this->taxonClass, 'taxon')
            ->leftJoin('taxon.children', 'child')
            ->leftJoin('child.translations', 'translation')
            ->andWhere('taxon = :taxon')
            ->setParameter('taxon', $taxon)
            ->getQuery()
            ->getResult();

        /** @var list<TaxonInterface> $children */
        $children = array_values($taxon->getChildren()->toArray());
        if ($children === []) {
            return;
        }

        $this->fetch('images', [$taxon, ...$children]);
        $this->fetch('featuredItemsTranslations', [$taxon, ...$children]);
        $this->fetch('featuredProducts', $children);

        $this->entityManager->createQueryBuilder()
            ->select('child', 'grandChild', 'translation')
            ->from($this->taxonClass, 'child')
            ->leftJoin('child.children', 'grandChild')
            ->leftJoin('grandChild.translations', 'translation')
            ->andWhere('child IN (:children)')
            ->setParameter('children', $children)
            ->getQuery()
            ->getResult();
    }

    /**
     * Fetch-joins one collection of the given taxons, which initializes it on the managed instances.
     *
     * @param list<TaxonInterface> $taxons
     */
    private function fetch(string $collection, array $taxons): void
    {
        if ($taxons === []) {
            return;
        }

        $this->entityManager->createQueryBuilder()
            ->select('taxon', 'item')
            ->from($this->taxonClass, 'taxon')
            ->leftJoin('taxon.' . $collection, 'item')
            ->andWhere('taxon IN (:taxons)')
            ->setParameter('taxons', $taxons)
            ->getQuery()
            ->getResult();
    }

    private function getTaxonKey(TaxonInterface $taxon): string
    {
        $id = $taxon->getId();

        return is_scalar($id) ? (string) $id : spl_object_hash($taxon);
    }
}
