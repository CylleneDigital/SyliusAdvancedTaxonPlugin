<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener\ConditionalTaxonSyncListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;

/**
 * The synchronization of a conditional taxon runs once the flush is over, never inside it.
 */
final class ConditionalTaxonSyncTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CatalogBuilder $catalog;

    private ConditionalTaxonSyncListener $listener;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
        $this->catalog = new CatalogBuilder(self::getContainer(), $this->entityManager);

        $listener = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.conditional_taxon.sync_listener');
        Assert::isInstanceOf($listener, ConditionalTaxonSyncListener::class);
        $this->listener = $listener;
        $this->listener->reset();
    }

    protected function tearDown(): void
    {
        $this->listener->reset();
        $this->entityManager->rollback();

        parent::tearDown();
    }

    public function test_the_products_are_assigned_once_the_request_is_over(): void
    {
        $channel = $this->catalog->channel('SYNC');
        $this->catalog->product('Sync steel watch', [$channel]);
        $taxon = $this->catalog->taxon('Sync watches');
        $taxon->addFacetCondition($this->nameCondition('Sync steel'));
        $this->entityManager->flush();

        self::assertSame(0, $this->countAssignedProducts($taxon), 'Nothing is synchronized during the flush.');

        $this->listener->dispatchPending();

        self::assertSame(1, $this->countAssignedProducts($taxon));
    }

    public function test_a_taxon_saved_by_a_console_command_is_synchronized_when_the_command_ends(): void
    {
        $channel = $this->catalog->channel('SYNC');
        $this->catalog->product('Sync gold watch', [$channel]);
        $taxon = $this->catalog->taxon('Sync gold');
        $taxon->addFacetCondition($this->nameCondition('Sync gold'));
        $this->entityManager->flush();

        $dispatcher = self::getContainer()->get('event_dispatcher');
        Assert::isInstanceOf($dispatcher, EventDispatcherInterface::class);
        $dispatcher->dispatch(new ConsoleTerminateEvent(new Command('app:import'), new ArrayInput([]), new NullOutput(), 0), ConsoleEvents::TERMINATE);

        self::assertSame(1, $this->countAssignedProducts($taxon));
    }

    /**
     * A collection emptied and filled again in the same flush is deleted, then inserted. The
     * synchronization must not replay the deletion once the insertion is done.
     */
    public function test_a_collection_replaced_in_the_same_flush_keeps_its_rows(): void
    {
        $taxon = $this->catalog->taxon('Sync parent');
        $first = $this->catalog->taxon('Sync first child', $taxon);
        $second = $this->catalog->taxon('Sync second child', $taxon);
        $taxon->addFeaturedChild($first);
        $this->entityManager->flush();

        $taxon->getFeaturedChildren()->clear();
        $taxon->addFeaturedChild($first);
        $taxon->addFeaturedChild($second);
        $taxon->addFacetCondition($this->nameCondition('anything'));
        $this->entityManager->flush();
        $this->listener->dispatchPending();

        $taxonId = $taxon->getId();
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find($taxon::class, $taxonId);
        Assert::isInstanceOf($reloaded, AdvancedTaxonInterface::class);

        self::assertCount(2, $reloaded->getFeaturedChildren());
    }

    private function nameCondition(string $value): FacetCondition
    {
        $condition = new FacetCondition();
        $condition->setConditionType(FacetCondition::TYPE_NAME);
        $condition->setOperator('contains');
        $condition->setValue($value);

        return $condition;
    }

    private function countAssignedProducts(AdvancedTaxonInterface $taxon): int
    {
        $productTaxonClass = self::getContainer()->getParameter('sylius.model.product_taxon.class');
        Assert::classExists($productTaxonClass);

        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pt.id)')
            ->from($productTaxonClass, 'pt')
            ->andWhere('pt.taxon = :taxon')
            ->setParameter('taxon', $taxon)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
