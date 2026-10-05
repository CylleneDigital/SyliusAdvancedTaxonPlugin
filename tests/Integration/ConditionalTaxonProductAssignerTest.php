<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\MessageHandler\SynchronizeConditionalTaxonHandler;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonProductAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class ConditionalTaxonProductAssignerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CatalogBuilder $catalog;

    private ConditionalTaxonProductAssigner $assigner;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
        $this->catalog = new CatalogBuilder(self::getContainer(), $this->entityManager);

        $assigner = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.conditional_taxon.synchronizer');
        Assert::isInstanceOf($assigner, ConditionalTaxonProductAssigner::class);
        $this->assigner = $assigner;
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        parent::tearDown();
    }

    /**
     * Sylius keeps the positions of a taxon contiguous (Gedmo sortable): the order is what matters.
     */
    public function test_the_remaining_products_keep_their_order_and_new_ones_come_last(): void
    {
        $channel = $this->catalog->channel('ASSIGN');
        $taxon = $this->conditionalTaxon('Assigned watches', 'Assigned watch');
        $first = $this->catalog->product('Assigned watch one', [$channel], $taxon);
        $leaving = $this->catalog->product('Assigned watch two', [$channel], $taxon);
        $moved = $this->catalog->product('Assigned watch three', [$channel], $taxon);
        // The merchant moves the third product to the top of the taxon.
        $this->productTaxon($taxon, (string) $moved->getCode())->setPosition(0);
        $this->entityManager->flush();

        $leaving->setName('Assigned ring');
        $newcomer = $this->catalog->product('Assigned watch four', [$channel]);
        $this->entityManager->flush();

        $result = $this->assigner->syncTaxon($taxon);
        $this->entityManager->flush();

        self::assertSame(['detached' => 1, 'attached' => 1, 'matched' => 3], $result);
        self::assertSame([(string) $moved->getCode(), (string) $first->getCode(), (string) $newcomer->getCode()], $this->orderedCodes($taxon));
    }

    public function test_a_taxon_that_is_not_conditional_any_more_loses_every_assignment(): void
    {
        $channel = $this->catalog->channel('ASSIGN');
        $taxon = $this->conditionalTaxon('Assigned caps', 'Assigned cap');
        $this->catalog->product('Assigned cap one', [$channel], $taxon);
        $this->catalog->product('Assigned cap two', [$channel], $taxon);
        $taxon->setConditional(false);
        $this->entityManager->flush();

        self::assertSame(['detached' => 2, 'attached' => 0, 'matched' => 0], $this->assigner->syncTaxon($taxon));
        $this->entityManager->flush();
        self::assertSame([], $this->orderedCodes($taxon));
    }

    public function test_the_handler_ignores_a_taxon_deleted_since_the_message_was_sent(): void
    {
        $handler = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.conditional_taxon.sync_handler');
        Assert::isInstanceOf($handler, SynchronizeConditionalTaxonHandler::class);

        // The largest id an INTEGER column holds on every platform.
        $handler(new SynchronizeConditionalTaxon(2147483647));

        self::assertSame([], $this->entityManager->getUnitOfWork()->getScheduledEntityInsertions());
    }

    private function conditionalTaxon(string $name, string $nameContains): AdvancedTaxonInterface
    {
        $condition = new FacetCondition();
        $condition->setConditionType(FacetCondition::TYPE_NAME);
        $condition->setOperator('contains');
        $condition->setValue($nameContains);

        $taxon = $this->catalog->taxon($name);
        $taxon->addFacetCondition($condition);
        $this->entityManager->flush();

        return $taxon;
    }

    private function productTaxon(AdvancedTaxonInterface $taxon, string $productCode): ProductTaxonInterface
    {
        foreach ($this->productTaxons($taxon) as $productTaxon) {
            if ($productTaxon->getProduct()?->getCode() === $productCode) {
                return $productTaxon;
            }
        }

        throw new \LogicException(sprintf('The product "%s" is not in the taxon.', $productCode));
    }

    /**
     * Read from the database: Gedmo shifts the positions with queries the loaded entities miss.
     *
     * @return list<string> product codes, in position order
     */
    private function orderedCodes(AdvancedTaxonInterface $taxon): array
    {
        $productTaxonClass = self::getContainer()->getParameter('sylius.model.product_taxon.class');
        Assert::classExists($productTaxonClass);

        /** @var list<array{code: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('product.code AS code')
            ->from($productTaxonClass, 'productTaxon')
            ->join('productTaxon.product', 'product')
            ->andWhere('productTaxon.taxon = :taxon')
            ->setParameter('taxon', $taxon)
            ->orderBy('productTaxon.position')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'code');
    }

    /**
     * @return list<ProductTaxonInterface>
     */
    private function productTaxons(AdvancedTaxonInterface $taxon): array
    {
        $productTaxonClass = self::getContainer()->getParameter('sylius.model.product_taxon.class');
        Assert::classExists($productTaxonClass);

        /** @var list<ProductTaxonInterface> $productTaxons */
        $productTaxons = $this->entityManager->getRepository($productTaxonClass)->findBy(['taxon' => $taxon]);

        return $productTaxons;
    }
}
