<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Domain;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener\ConditionalTaxonSyncListener;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

/**
 * Covers the materialization of conditional taxons: the differential synchronization triggered by a
 * Doctrine flush, and the recurring synchronization performed by the console command.
 */
final class MaterializingConditionalTaxonsContext implements Context
{
    private const COMMAND_NAME = 'cyllene:advanced-taxon:sync-conditional-taxons';

    /**
     * @param FactoryInterface<TaxonInterface> $taxonFactory
     * @param RepositoryInterface<TaxonInterface> $taxonRepository
     * @param RepositoryInterface<ProductInterface> $productRepository
     * @param class-string $productTaxonClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $taxonFactory,
        private readonly RepositoryInterface $taxonRepository,
        private readonly RepositoryInterface $productRepository,
        private readonly KernelInterface $kernel,
        private readonly ConditionalTaxonSyncListener $syncListener,
        private readonly string $productTaxonClass,
    ) {
    }

    #[Given('the taxon :name matches products whose name contains :phrase')]
    public function theTaxonMatchesProductsWhoseNameContains(string $name, string $phrase): void
    {
        $taxon = $this->createConditionalTaxon($name);
        $taxon->addFacetCondition($this->createNameCondition($phrase));

        $this->taxonRepository->add($taxon);
        $this->endRequest();
    }

    #[Given('the taxon :name has an attribute condition without attribute')]
    public function theTaxonHasAnAttributeConditionWithoutAttribute(string $name): void
    {
        $condition = new FacetCondition();
        $condition->setConditionType(FacetCondition::TYPE_ATTRIBUTE);
        $condition->setOperator('contains');
        $condition->setValue('anything');

        $taxon = $this->createConditionalTaxon($name);
        $taxon->addFacetCondition($condition);

        $this->taxonRepository->add($taxon);
        $this->endRequest();
    }

    #[When('I reconfigure the taxon :name to match products whose name contains :phrase')]
    public function iReconfigureTheTaxonToMatchProductsWhoseNameContains(string $name, string $phrase): void
    {
        $taxon = $this->getTaxon($name);
        $taxon->getFacetConditions()->clear();
        $taxon->addFacetCondition($this->createNameCondition($phrase));

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('I change the value of the condition of the taxon :name to :phrase')]
    public function iChangeTheValueOfTheConditionOfTheTaxonTo(string $name, string $phrase): void
    {
        $condition = $this->getTaxon($name)->getFacetConditions()->first();
        Assert::isInstanceOf($condition, FacetCondition::class);

        $condition->setValue($phrase);

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[Given('the taxon :name also matches products whose name contains :phrase')]
    public function theTaxonAlsoMatchesProductsWhoseNameContains(string $name, string $phrase): void
    {
        $this->getTaxon($name)->addFacetCondition($this->createNameCondition($phrase));

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('I remove the condition on :phrase from the taxon :name')]
    public function iRemoveTheConditionFromTheTaxon(string $phrase, string $name): void
    {
        $taxon = $this->getTaxon($name);
        foreach ($taxon->getFacetConditions() as $condition) {
            if ($condition->getValue() === $phrase) {
                $taxon->removeFacetCondition($condition);
            }
        }

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('I remove every condition of the taxon :name')]
    public function iRemoveEveryConditionOfTheTaxon(string $name): void
    {
        $taxon = $this->getTaxon($name);
        foreach ($taxon->getFacetConditions()->toArray() as $condition) {
            $taxon->removeFacetCondition($condition);
        }

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('I rename the taxon :name to :newName')]
    public function iRenameTheTaxon(string $name, string $newName): void
    {
        $taxon = $this->getTaxon($name);
        $taxon->setCurrentLocale('en_US');
        $taxon->setName($newName);

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('the product :productName is disabled')]
    public function theProductIsDisabled(string $productName): void
    {
        $this->getProduct($productName)->setEnabled(false);

        $this->entityManager->flush();
        $this->endRequest();
    }

    #[When('I delete the taxon :name')]
    public function iDeleteTheTaxon(string $name): void
    {
        $this->entityManager->remove($this->getTaxon($name));
        $this->entityManager->flush();
        $this->endRequest();
    }

    #[Then('the taxon :name should not exist any more')]
    public function theTaxonShouldNotExistAnyMore(string $name): void
    {
        Assert::null($this->taxonRepository->findOneBy(['code' => $this->toCode($name)]));
    }

    #[Then('the product :productName should not belong to any taxon')]
    public function theProductShouldNotBelongToAnyTaxon(string $productName): void
    {
        $assigned = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pt.id)')
            ->from($this->productTaxonClass, 'pt')
            ->andWhere('pt.product = :product')
            ->setParameter('product', $this->getProduct($productName))
            ->getQuery()
            ->getSingleScalarResult();

        Assert::same(0, $assigned, sprintf('The product "%s" still belongs to %d taxon(s).', $productName, $assigned));
    }

    #[When('I run the conditional taxon synchronization command')]
    public function iRunTheConditionalTaxonSynchronizationCommand(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $tester = new CommandTester($application->find(self::COMMAND_NAME));
        $tester->execute([]);

        Assert::same(0, $tester->getStatusCode(), sprintf('The "%s" command failed: %s', self::COMMAND_NAME, $tester->getDisplay()));
    }

    #[Then('the taxon :name should have :count assigned product(s)')]
    public function theTaxonShouldHaveAssignedProducts(string $name, int $count): void
    {
        $assigned = $this->countAssignedProducts($this->getTaxon($name));

        Assert::same(
            $count,
            $assigned,
            sprintf('Expected %d assigned products for the taxon "%s", got %d.', $count, $name, $assigned),
        );
    }

    #[Then('the taxon :taxonName should be assigned the product :productName')]
    public function theTaxonShouldBeAssignedTheProduct(string $taxonName, string $productName): void
    {
        $taxon = $this->getTaxon($taxonName);
        $product = $this->getProduct($productName);

        $assigned = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pt.id)')
            ->from($this->productTaxonClass, 'pt')
            ->andWhere('pt.taxon = :taxon')
            ->andWhere('pt.product = :product')
            ->setParameter('taxon', $taxon)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        Assert::same(1, $assigned, sprintf('The taxon "%s" is not assigned the product "%s".', $taxonName, $productName));
    }

    #[Then('the taxon :name should be conditional')]
    public function theTaxonShouldBeConditional(string $name): void
    {
        Assert::true($this->getTaxon($name)->isConditional(), sprintf('The taxon "%s" is not conditional.', $name));
    }

    #[Then('the taxon :name should not be conditional')]
    public function theTaxonShouldNotBeConditional(string $name): void
    {
        Assert::false($this->getTaxon($name)->isConditional(), sprintf('The taxon "%s" is still conditional.', $name));
    }

    #[Then('the taxon :name should have no condition')]
    public function theTaxonShouldHaveNoCondition(string $name): void
    {
        Assert::isEmpty($this->getTaxon($name)->getFacetConditions()->toArray(), sprintf('The taxon "%s" has stored conditions.', $name));
    }

    /**
     * The synchronization messages go out once the request is over, not during the flush.
     */
    private function endRequest(): void
    {
        $this->syncListener->dispatchPending();
    }

    private function createConditionalTaxon(string $name): AdvancedTaxonInterface
    {
        $taxon = $this->taxonFactory->createNew();
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class);

        $taxon->setCode($this->toCode($name));
        $taxon->setName($name);
        $taxon->setSlug($this->toCode($name));
        $taxon->setEnabled(true);
        $taxon->setConditional(true);

        return $taxon;
    }

    private function createNameCondition(string $phrase): FacetCondition
    {
        $condition = new FacetCondition();
        $condition->setConditionType(FacetCondition::TYPE_NAME);
        $condition->setOperator('contains');
        $condition->setValue($phrase);

        return $condition;
    }

    private function getTaxon(string $name): AdvancedTaxonInterface
    {
        $taxon = $this->taxonRepository->findOneBy(['code' => $this->toCode($name)]);
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class, sprintf('The taxon "%s" was not found.', $name));

        // A browser scenario saves the taxon in another process: the entity in memory may be stale.
        $this->entityManager->refresh($taxon);

        return $taxon;
    }

    private function getProduct(string $name): ProductInterface
    {
        // Same code as the Sylius product setup steps: an exact match on case-sensitive databases.
        $product = $this->productRepository->findOneBy(['code' => StringInflector::nameToUppercaseCode($name)]);
        Assert::isInstanceOf($product, ProductInterface::class, sprintf('The product "%s" was not found.', $name));

        return $product;
    }

    private function countAssignedProducts(AdvancedTaxonInterface $taxon): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pt.id)')
            ->from($this->productTaxonClass, 'pt')
            ->andWhere('pt.taxon = :taxon')
            ->setParameter('taxon', $taxon)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function toCode(string $value): string
    {
        return strtolower(str_replace([' ', '-'], '_', $value));
    }
}
