<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\ChildTaxonAutocompleteType;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\TaxonAttachedProductAutocompleteType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Webmozart\Assert\Assert;

/**
 * The autocomplete fields of the taxon form must keep the search on the typed text that Sylius adds,
 * and restrict the candidates to the children, or to the products, of the edited taxon.
 */
final class AutocompleteTypesTest extends KernelTestCase
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

    public function test_the_child_taxon_choices_are_the_children_matching_the_typed_text(): void
    {
        $watches = $this->catalog->taxon('Watches');
        $sport = $this->catalog->taxon('Sport watches', $watches);
        $this->catalog->taxon('Classic watches', $watches);
        $this->catalog->taxon('Sport shoes');
        $this->entityManager->flush();

        $names = array_map(
            static fn (TaxonInterface $taxon): ?string => $taxon->getName(),
            $this->search(ChildTaxonAutocompleteType::class, TaxonInterface::class, ['parent_taxon_id' => $watches->getId()], 'Sport'),
        );

        self::assertSame([$sport->getName()], $names);
    }

    public function test_the_attached_product_choices_are_the_products_of_the_taxon_matching_the_typed_text(): void
    {
        $channel = $this->catalog->channel('AUTOCOMPLETE');
        $watches = $this->catalog->taxon('Watches');
        $steel = $this->catalog->product('Steel watch', [$channel], $watches);
        $this->catalog->product('Gold watch', [$channel], $watches);
        $this->catalog->product('Steel ring', [$channel]);
        $this->entityManager->flush();

        $names = array_map(
            static fn (ProductInterface $product): ?string => $product->getName(),
            $this->search(TaxonAttachedProductAutocompleteType::class, ProductInterface::class, ['taxon_id' => $watches->getId()], 'Steel'),
        );

        self::assertSame([$steel->getName()], $names);
    }

    public function test_a_field_built_without_the_taxon_id_offers_no_choice(): void
    {
        // The form of a taxon not saved yet passes no id.
        $this->catalog->taxon('Orphan child', $this->catalog->taxon('Unsaved parent'));
        $this->entityManager->flush();

        self::assertSame([], $this->search(ChildTaxonAutocompleteType::class, TaxonInterface::class, [], 'Orphan'));
    }

    /**
     * Runs the filter query of the form type the way UX Autocomplete does on a search request.
     *
     * @template T of object
     *
     * @param class-string $formType
     * @param class-string<T> $resultClass
     * @param array<string, mixed> $extraOptions
     *
     * @return list<T>
     */
    private function search(string $formType, string $resultClass, array $extraOptions, string $query): array
    {
        $formFactory = self::getContainer()->get('form.factory');
        Assert::isInstanceOf($formFactory, FormFactoryInterface::class);

        $form = $formFactory->create($formType, null, ['extra_options' => $extraOptions]);
        $filterQuery = $form->getConfig()->getOption('filter_query');
        Assert::isCallable($filterQuery);

        $class = $form->getConfig()->getOption('class');
        Assert::classExists($class);
        $repository = $this->entityManager->getRepository($class);
        Assert::isInstanceOf($repository, EntityRepository::class);

        $queryBuilder = $repository->createQueryBuilder('entity');
        $filterQuery($queryBuilder, $query, $repository);

        $rows = $queryBuilder->getQuery()->getResult();
        Assert::isArray($rows);

        $results = [];
        foreach ($rows as $result) {
            Assert::isInstanceOf($result, $resultClass);
            $results[] = $result;
        }

        return $results;
    }
}
