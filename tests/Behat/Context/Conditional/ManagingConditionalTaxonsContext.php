<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Conditional;

use Behat\Behat\Context\Context;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

/**
 * Covers the materialization of conditional taxons: the differential synchronization triggered by a
 * Doctrine flush, and the recurring synchronization performed by the console command.
 */
final class ManagingConditionalTaxonsContext implements Context
{
    private const COMMAND_NAME = 'cyllene:advanced-taxon:sync-conditional-taxons';

    /**
     * @param class-string $productTaxonClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $taxonFactory,
        private readonly RepositoryInterface $taxonRepository,
        private readonly FactoryInterface $productFactory,
        private readonly RepositoryInterface $productRepository,
        private readonly RepositoryInterface $channelRepository,
        private readonly KernelInterface $kernel,
        private readonly string $productTaxonClass,
    ) {
    }

    /**
     * @Given the store has a product :name
     */
    public function theStoreHasAProduct(string $name): void
    {
        $channel = $this->channelRepository->findOneBy([]);
        Assert::isInstanceOf($channel, ChannelInterface::class, 'No channel was found, create one first.');

        $product = $this->productFactory->createNew();
        Assert::isInstanceOf($product, ProductInterface::class);

        $product->setCode($this->toCode($name));
        $product->setName($name);
        $product->setSlug($this->toCode($name));
        $product->addChannel($channel);

        $this->productRepository->add($product);
    }

    /**
     * @When the taxon :name matches products whose name contains :phrase
     */
    public function theTaxonMatchesProductsWhoseNameContains(string $name, string $phrase): void
    {
        $taxon = $this->createConditionalTaxon($name);
        $taxon->addFacetCondition($this->createNameCondition($phrase));

        $this->taxonRepository->add($taxon);
    }

    /**
     * @When I reconfigure the taxon :name to match products whose name contains :phrase
     */
    public function iReconfigureTheTaxonToMatchProductsWhoseNameContains(string $name, string $phrase): void
    {
        $taxon = $this->getTaxon($name);
        $taxon->getFacetConditions()->clear();
        $taxon->addFacetCondition($this->createNameCondition($phrase));

        $this->entityManager->flush();
    }

    /**
     * @When I run the conditional taxon synchronization command
     */
    public function iRunTheConditionalTaxonSynchronizationCommand(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $tester = new CommandTester($application->find(self::COMMAND_NAME));
        $tester->execute([]);

        Assert::same(0, $tester->getStatusCode(), sprintf('The "%s" command failed: %s', self::COMMAND_NAME, $tester->getDisplay()));
    }

    /**
     * @Then the taxon :name should have :count assigned product(s)
     */
    public function theTaxonShouldHaveAssignedProducts(string $name, int $count): void
    {
        $assigned = $this->countAssignedProducts($this->getTaxon($name));

        Assert::same(
            $count,
            $assigned,
            sprintf('Expected %d assigned products for the taxon "%s", got %d.', $count, $name, $assigned),
        );
    }

    /**
     * @Then the taxon :taxonName should be assigned the product :productName
     */
    public function theTaxonShouldBeAssignedTheProduct(string $taxonName, string $productName): void
    {
        $taxon = $this->getTaxon($taxonName);
        $product = $this->productRepository->findOneBy(['code' => $this->toCode($productName)]);
        Assert::isInstanceOf($product, ProductInterface::class, sprintf('The product "%s" was not found.', $productName));

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

    /**
     * @Then the taxon :name should be conditional
     */
    public function theTaxonShouldBeConditional(string $name): void
    {
        Assert::true($this->getTaxon($name)->isConditional(), sprintf('The taxon "%s" is not conditional.', $name));
    }

    private function createConditionalTaxon(string $name): Taxon
    {
        $taxon = $this->taxonFactory->createNew();
        Assert::isInstanceOf($taxon, Taxon::class);

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

    private function getTaxon(string $name): Taxon
    {
        $taxon = $this->taxonRepository->findOneBy(['code' => $this->toCode($name)]);
        Assert::isInstanceOf($taxon, Taxon::class, sprintf('The taxon "%s" was not found.', $name));

        return $taxon;
    }

    private function countAssignedProducts(Taxon $taxon): int
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
