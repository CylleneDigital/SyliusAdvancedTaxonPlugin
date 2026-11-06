<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Functional;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DomCrawler\Crawler;
use Webmozart\Assert\Assert;

final class AdminTaxonTreeTest extends AdminTestCase
{
    private const COMPONENT = '[data-live-name-value="cyllene_digital_sylius_advanced_taxon:taxon:tree"]';

    public function test_the_taxon_tree_is_rendered_whole_without_a_search(): void
    {
        [$men, $shirts, $women] = $this->createTree();

        $crawler = $this->editTree($men);

        self::assertSame('debounce(300)|search', $crawler->filter('[data-test-taxon-tree-search]')->attr('data-model'));

        // The openness is read from the tree data, so the Sylius tree controller leaves autoOpen off.
        $component = $crawler->filter(self::COMPONENT)->first();
        self::assertSame('false', $component->attr('data-sylius--admin-bundle--taxon-tree-auto-open-value'));

        $names = $this->treeNames($crawler);
        self::assertContains($men->getName(), $names);
        self::assertContains($shirts->getName(), $names);
        self::assertContains($women->getName(), $names);
        self::assertSame('', trim($crawler->filter('[data-test-taxon-tree-no-result]')->text()));
    }

    public function test_the_search_field_is_also_shown_when_creating_a_taxon(): void
    {
        $crawler = $this->client->request('GET', '/admin/taxons/new');
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('[data-test-taxon-tree-search]'));
    }

    public function test_searching_a_taxon_keeps_its_branch_and_drops_the_other_ones(): void
    {
        [$men, $shirts, $women] = $this->createTree();

        $filtered = $this->search($this->editTree($men), (string) $shirts->getName());

        $names = $this->treeNames($filtered);
        self::assertContains($shirts->getName(), $names);
        // The ancestor stays, so that the found taxon keeps its place in the catalog.
        self::assertContains($men->getName(), $names);
        self::assertNotContains($women->getName(), $names);
        self::assertSame('', trim($filtered->filter('[data-test-taxon-tree-no-result]')->text()));
    }

    public function test_searching_an_unknown_taxon_says_so_instead_of_listing_the_catalog(): void
    {
        [$men] = $this->createTree();
        $term = uniqid('no taxon is named ', false);

        $filtered = $this->search($this->editTree($men), $term);

        self::assertSame([], $this->treeNames($filtered));
        self::assertSame(
            sprintf('No taxon name matches "%s".', $term),
            $filtered->filter('[data-test-taxon-tree-no-result]')->text(),
        );
    }

    public function test_a_small_tree_is_opened_so_that_every_taxon_stays_visible(): void
    {
        if ($this->countTaxons() > 100) {
            // The catalog of a database shared with other tests decide the folding on its own.
            self::markTestSkipped('The test database holds more than 100 taxons already.');
        }

        [$men, $shirts] = $this->createTree();

        $node = $this->taxonNamed($this->treeData($this->editTree($men)), $this->nameOf($men));

        self::assertTrue($this->isOpen($node), 'The taxons of a small tree stay open.');
        self::assertSame([$shirts->getName()], array_column($this->childrenOf($node), 'name'));
    }

    public function test_a_large_tree_is_folded_down_to_its_roots_until_a_term_is_searched(): void
    {
        $warehouse = $this->createTaxon(uniqid('Warehouse ', false));
        $childNames = $this->createChildren($warehouse, 100);
        self::assertCount(100, $childNames);

        $crawler = $this->editTree($warehouse);
        $name = $this->nameOf($warehouse);

        self::assertFalse(
            $this->isOpen($this->taxonNamed($this->treeData($crawler), $name)),
            'A large tree arrives folded down to its roots.',
        );

        // A search opens the branch it matches, so the found taxon is visible straight away.
        self::assertTrue(
            $this->isOpen($this->taxonNamed($this->treeData($this->search($crawler, $childNames[0])), $name)),
        );

        // Clearing the term folds the tree back.
        self::assertFalse(
            $this->isOpen($this->taxonNamed($this->treeData($this->search($crawler, '')), $name)),
        );
    }

    private function countTaxons(): int
    {
        $taxonClass = self::getContainer()->getParameter('sylius.model.taxon.class');
        Assert::string($taxonClass);

        return (int) $this->entityManager
            ->createQuery(sprintf('SELECT COUNT(taxon.id) FROM %s taxon', $taxonClass))
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    private function createChildren(TaxonInterface $parent, int $count): array
    {
        // One flush for the whole branch: the nested set is computed once per tree root.
        $names = [];
        for ($i = 1; $i <= $count; ++$i) {
            $name = sprintf('%s child %03d', $parent->getName(), $i);
            $this->createTaxon($name, $parent, flush: false);
            $names[] = $name;
        }
        $this->flush();

        return $names;
    }

    /**
     * @param list<array<array-key, mixed>> $nodes
     *
     * @return array<array-key, mixed>
     */
    private function taxonNamed(array $nodes, string $name): array
    {
        foreach ($nodes as $node) {
            if (($node['name'] ?? null) === $name) {
                return $node;
            }
        }

        self::fail(sprintf('The tree holds no taxon named "%s".', $name));
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function isOpen(array $node): bool
    {
        $state = $node['state'] ?? null;
        Assert::isArray($state);

        return (bool) ($state['open'] ?? false);
    }

    /**
     * The taxons the tree data nests under this one.
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<array<array-key, mixed>>
     */
    private function childrenOf(array $node): array
    {
        $children = $node['children'] ?? [];
        Assert::isArray($children);

        return array_values(array_filter($children, is_array(...)));
    }

    private function nameOf(TaxonInterface $taxon): string
    {
        Assert::string($taxon->getName());

        return (string) $taxon->getName();
    }

    /**
     * The tree the component hands to the Sylius tree controller, as the browser receives it.
     *
     * @return list<array<array-key, mixed>>
     */
    private function treeData(Crawler $crawler): array
    {
        $attribute = 'data-sylius--admin-bundle--taxon-tree-tree-data-value';
        $decoded = json_decode((string) $crawler->filter('[' . $attribute . ']')->first()->attr($attribute), true, 512, \JSON_THROW_ON_ERROR);
        Assert::isArray($decoded);

        return array_values(array_filter($decoded, is_array(...)));
    }

    /**
     * @return array{AdvancedTaxonInterface, AdvancedTaxonInterface, AdvancedTaxonInterface}
     */
    private function createTree(): array
    {
        // Unique: a taxon of the same name left in the database by another test would be filtered too.
        $men = $this->createTaxon(uniqid('Men ', false));
        $shirts = $this->createTaxon(uniqid('Shirts ', false), $men);
        $women = $this->createTaxon(uniqid('Women ', false));

        return [$men, $shirts, $women];
    }

    private function editTree(TaxonInterface $taxon): Crawler
    {
        $taxonId = $taxon->getId();
        self::assertIsInt($taxonId);

        $crawler = $this->client->request('GET', sprintf('/admin/taxons/%d/edit', $taxonId));
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Posts a search to the live component of the tree, as the search field does in the browser.
     */
    private function search(Crawler $crawler, string $term): Crawler
    {
        $component = $crawler->filter(self::COMPONENT)->first();

        $this->client->request(
            'POST',
            (string) $component->attr('data-live-url-value'),
            ['data' => json_encode([
                'props' => json_decode((string) $component->attr('data-live-props-value'), true, 512, \JSON_THROW_ON_ERROR),
                'updated' => ['search' => $term],
            ], \JSON_THROW_ON_ERROR)],
            [],
            ['HTTP_ACCEPT' => 'application/vnd.live-component+html'],
        );
        self::assertResponseIsSuccessful();

        return new Crawler((string) $this->client->getResponse()->getContent());
    }

    /**
     * @return list<string>
     */
    private function treeNames(Crawler $crawler): array
    {
        $attribute = 'data-sylius--admin-bundle--taxon-tree-tree-data-value';
        $tree = json_decode((string) $crawler->filter('[' . $attribute . ']')->first()->attr($attribute), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($tree);

        return $this->flattenNames($tree);
    }

    /**
     * @param array<array-key, mixed> $nodes
     *
     * @return list<string>
     */
    private function flattenNames(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $name = $node['name'] ?? null;
            if (is_string($name)) {
                $names[] = $name;
            }

            $children = $node['children'] ?? null;
            if (is_array($children)) {
                $names = [...$names, ...$this->flattenNames($children)];
            }
        }

        return $names;
    }
}
