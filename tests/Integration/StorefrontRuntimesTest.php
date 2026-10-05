<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\MenuRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\VisibilityRuntime;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class StorefrontRuntimesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CatalogBuilder $catalog;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
        $this->catalog = new CatalogBuilder(self::getContainer(), $this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        parent::tearDown();
    }

    public function test_the_universe_page_reads_its_children_in_a_fixed_number_of_queries(): void
    {
        $channel = $this->catalog->channel('UNIVERSE');
        $universe = $this->catalog->taxon('Universe');
        foreach (['Men', 'Women', 'Kids'] as $name) {
            $child = $this->catalog->taxon($name, $universe);
            $this->catalog->taxon($name . ' sale', $child);
            $child->addFeaturedProduct($this->catalog->product($name . ' jeans', [$channel]));
            $child->getOrCreateFeaturedItemsTranslation('en_US')->setFeaturedProductsTitle($name . ' picks');
        }
        $this->entityManager->flush();
        $universeId = $universe->getId();
        $this->entityManager->clear();
        $taxonClass = self::getContainer()->getParameter('sylius.model.taxon.class');
        Assert::classExists($taxonClass);
        $universe = $this->entityManager->find($taxonClass, $universeId);
        Assert::isInstanceOf($universe, AdvancedTaxonInterface::class);

        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        Assert::isInstanceOf($queries, BacktraceDebugDataHolder::class);
        $menu = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.twig.runtime.menu');
        Assert::isInstanceOf($menu, MenuRuntime::class);

        $queries->reset();
        $menu->preloadUniverse($universe);
        $preloadQueries = count($queries->getData()['default']);

        foreach ($universe->getChildren() as $child) {
            Assert::isInstanceOf($child, AdvancedTaxonInterface::class);
            $child->getName();
            $child->getFeaturedProductsTitle('en_US');
            count($child->getFeaturedProducts());
            count($child->getImages());
            foreach ($child->getChildren() as $grandChild) {
                $grandChild->getName();
            }
        }

        self::assertSame(5, $preloadQueries);
        self::assertCount(5, $queries->getData()['default'], 'Nothing is read child by child.');
    }

    public function test_featured_products_not_loaded_yet_are_checked_against_the_channel(): void
    {
        $web = $this->catalog->channel('WEB');
        $other = $this->catalog->channel('OTHER');
        $inChannel = $this->catalog->product('In channel', [$web]);
        $elsewhere = $this->catalog->product('Elsewhere', [$other]);
        $ids = [$inChannel->getId(), $elsewhere->getId()];
        $webId = $web->getId();
        $this->entityManager->clear();

        /** @var class-string<ProductInterface> $productClass */
        $productClass = self::getContainer()->getParameter('sylius.model.product.class');
        $products = $this->entityManager->getRepository($productClass)->findBy(['id' => $ids], ['id' => 'ASC']);
        $channel = $this->entityManager->find($web::class, $webId);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $visibility = new VisibilityRuntime($channelContext, $this->entityManager, $productClass);

        $visible = array_map(static fn (ProductInterface $product): ?string => $product->getName(), $visibility->visibleProducts($products));

        self::assertSame(['In channel'], $visible);
    }
}
