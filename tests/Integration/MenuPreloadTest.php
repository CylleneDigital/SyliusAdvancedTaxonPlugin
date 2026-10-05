<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\MenuRuntime;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class MenuPreloadTest extends KernelTestCase
{
    public function test_it_loads_what_the_menu_reads_in_a_fixed_number_of_queries(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $entityManager->beginTransaction();

        try {
            $catalog = new CatalogBuilder(self::getContainer(), $entityManager);
            $root = $catalog->taxon('Menu root');
            $clothing = $catalog->taxon('Clothing', $root);
            $shirts = $catalog->taxon('Shirts', $clothing);
            $catalog->taxon('Trousers', $clothing);
            $catalog->taxon('Polos', $shirts);
            $clothing->addFeaturedChild($shirts);
            $entityManager->flush();

            $clothingId = $clothing->getId();
            $shirtsId = $shirts->getId();
            $entityManager->clear();
            $clothing = $entityManager->find($clothing::class, $clothingId);
            Assert::isInstanceOf($clothing, AdvancedTaxonInterface::class);

            $queries = self::getContainer()->get('doctrine.debug_data_holder');
            Assert::isInstanceOf($queries, BacktraceDebugDataHolder::class);
            $queries->reset();

            $menu = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.twig.runtime.menu');
            Assert::isInstanceOf($menu, MenuRuntime::class);
            $menu->preloadMenu([$clothing]);

            // Subtree with children and translations, then featured children, then media.
            self::assertCount(3, $queries->getData()['default'] ?? []);

            // Already in the identity map: no query, and its collections are not touched.
            $shirts = $entityManager->find($clothing::class, $shirtsId);
            Assert::isInstanceOf($shirts, AdvancedTaxonInterface::class);

            foreach ([$clothing->getChildren(), $clothing->getFeaturedChildren(), $clothing->getImages(), $shirts->getChildren(), $shirts->getFeaturedChildren(), $shirts->getImages()] as $collection) {
                self::assertTrue(!$collection instanceof PersistentCollection || $collection->isInitialized());
            }
            self::assertCount(2, $clothing->getChildren());
            self::assertCount(1, $clothing->getFeaturedChildren());
            self::assertCount(1, $shirts->getChildren());
            self::assertCount(3, $queries->getData()['default'] ?? []);
        } finally {
            $entityManager->rollback();
        }
    }
}
