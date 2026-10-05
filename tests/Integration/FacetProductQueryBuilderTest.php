<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Grid\ShopProductListQueryBuilder;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonQueryBuilder;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceCheckerInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\SelectAttributeValueResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

final class FacetProductQueryBuilderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CatalogBuilder $catalog;

    private ?ChannelInterface $currentChannel = null;

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

    public function test_the_stock_condition_deducts_the_reserved_quantity(): void
    {
        $channel = $this->catalog->channel('web');
        $reserved = $this->catalog->product('Reserved', [$channel]);
        $this->catalog->variant($reserved, $channel, 1000, onHand: 2, onHold: 2);
        $untracked = $this->catalog->product('Untracked', [$channel]);
        $this->catalog->variant($untracked, $channel, 1000, onHand: 0, tracked: false);

        $names = $this->matchingNames([$this->condition(FacetCondition::TYPE_STOCK, 'is_in_stock')], [$reserved, $untracked]);

        self::assertSame(['Untracked'], $names);
    }

    public function test_like_wildcards_typed_by_the_merchant_are_literal(): void
    {
        $channel = $this->catalog->channel('web');
        $percent = $this->catalog->product('Shirt 100% cotton', [$channel]);
        $other = $this->catalog->product('Shirt 1000 cotton', [$channel]);

        $names = $this->matchingNames([$this->condition(FacetCondition::TYPE_NAME, 'contains', null, '100%')], [$percent, $other]);

        self::assertSame(['Shirt 100% cotton'], $names);
    }

    public function test_an_attribute_condition_matches_a_select_attribute_by_label(): void
    {
        $channel = $this->catalog->channel('web');
        $color = $this->catalog->attribute('color', 'select', 'Color', [
            'choices' => ['red_key' => ['en_US' => 'Red'], 'blue_key' => ['en_US' => 'Blue']],
            'multiple' => false,
            'min' => null,
            'max' => null,
        ]);
        $red = $this->catalog->product('Red cap', [$channel]);
        $this->catalog->attributeValue($red, $color, ['red_key']);
        $blue = $this->catalog->product('Blue cap', [$channel]);
        $this->catalog->attributeValue($blue, $color, ['blue_key']);

        $condition = $this->condition(FacetCondition::TYPE_ATTRIBUTE, 'equals', (string) $color->getCode(), 'red');
        $negated = $this->condition(FacetCondition::TYPE_ATTRIBUTE, 'not_equals', (string) $color->getCode(), 'red');

        self::assertSame(['Red cap'], $this->matchingNames([$condition], [$red, $blue]));
        self::assertSame(['Blue cap'], $this->matchingNames([$negated], [$red, $blue]));
    }

    public function test_attribute_facets_are_localized_and_include_select_attributes(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Caps');
        $material = $this->catalog->attribute('material', 'text', 'Material');
        $color = $this->catalog->attribute('color', 'select', 'Color', [
            'choices' => ['red_key' => ['en_US' => 'Red', 'fr_FR' => 'Rouge']],
            'multiple' => false,
            'min' => null,
            'max' => null,
        ]);
        $cap = $this->catalog->product('Cap', [$channel], $taxon);
        $this->catalog->attributeValue($cap, $material, 'Cotton', 'en_US');
        $this->catalog->attributeValue($cap, $material, 'Coton', 'fr_FR');
        $this->catalog->attributeValue($cap, $color, ['red_key']);
        $this->catalog->product('Plain cap', [$channel], $taxon);

        $facets = $this->facets($taxon, [])['attributes'];

        self::assertSame(
            [
                ['code' => (string) $color->getCode(), 'values' => [['value' => 'red_key', 'label' => 'Red', 'count' => 1]]],
                ['code' => (string) $material->getCode(), 'values' => [['value' => 'Cotton', 'label' => 'Cotton', 'count' => 1]]],
            ],
            array_map(static fn (array $facet): array => [
                'code' => $facet['code'],
                'values' => array_map(static fn (array $value): array => ['value' => $value['value'], 'label' => $value['label'], 'count' => $value['count']], $facet['values']),
            ], $facets),
        );

        $filtered = $this->filteredNames($taxon, ['attributes' => [(string) $color->getCode() => ['red_key']]]);
        self::assertSame(['Cap'], $filtered);
    }

    public function test_option_facets_use_the_translated_option_name(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $sizes = $this->catalog->option('size', 'Size', ['S']);
        $shirt = $this->catalog->product('Shirt', [$channel], $taxon);
        $this->catalog->variant($shirt, $channel, 1000, [$sizes['S']]);

        $facets = $this->facets($taxon, [])['options'];

        self::assertCount(1, $facets);
        self::assertSame('Size', $facets[0]['label']);
    }

    public function test_option_and_price_filters_apply_to_the_same_variant(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $shirt = $this->catalog->product('Shirt', [$channel], $taxon);
        $this->catalog->variant($shirt, $channel, 1000, [$sizes['S']]);
        $this->catalog->variant($shirt, $channel, 5000, [$sizes['M']]);

        $sizeCode = (string) $sizes['S']->getOption()?->getCode();

        self::assertSame([], $this->filteredNames($taxon, ['options' => [$sizeCode => ['S']], 'price' => ['min' => '40']]));
        self::assertSame(['Shirt'], $this->filteredNames($taxon, ['options' => [$sizeCode => ['M']], 'price' => ['min' => '40']]));
    }

    public function test_storefront_queries_only_return_products_of_the_current_channel(): void
    {
        $web = $this->catalog->channel('web');
        $b2b = $this->catalog->channel('b2b');
        $this->currentChannel = $web;
        $taxon = $this->catalog->taxon('Caps');
        $this->catalog->product('Web cap', [$web], $taxon);
        $this->catalog->product('B2B cap', [$b2b], $taxon);

        self::assertSame(['Web cap'], $this->filteredNames($taxon, []));
    }

    public function test_an_incomplete_stored_condition_matches_nothing(): void
    {
        $channel = $this->catalog->channel('web');
        $product = $this->catalog->product('Anything', [$channel]);

        $complete = $this->condition(FacetCondition::TYPE_NAME, 'contains', null, 'Any');
        $incomplete = $this->condition(FacetCondition::TYPE_ATTRIBUTE, 'contains', null, 'x');

        self::assertSame(['Anything'], $this->matchingNames([$complete], [$product]));
        self::assertSame([], $this->matchingNames([$complete, $incomplete], [$product]));
    }

    public function test_the_grid_query_paginates_children_products_without_duplicates(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $parent = $this->catalog->taxon('Clothing');
        $parent->setIncludeChildrenProducts(true);
        $shirts = $this->catalog->taxon('Shirts', $parent);
        $polos = $this->catalog->taxon('Polos', $parent);
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);

        for ($i = 1; $i <= 12; ++$i) {
            $product = $this->catalog->product(sprintf('Shirt %02d', $i), [$channel], $shirts);
            // Half of the products sit in two children and own several variants: rows the joins multiply.
            if ($i % 2 === 0) {
                $this->catalog->assign($product, $polos);
            }
            $this->catalog->variant($product, $channel, 1000 * $i, [$sizes['S']]);
            $this->catalog->variant($product, $channel, 1000 * $i + 1, [$sizes['M']]);
        }

        $sizeCode = (string) $sizes['S']->getOption()?->getCode();
        foreach ([[], ['price' => 'desc'], ['name' => 'asc']] as $sorting) {
            // As the Sylius grid paginates: fetch join collection and output walkers on.
            $pager = new Pagerfanta(new QueryAdapter($this->listQuery($parent, ['options' => [$sizeCode => ['S']]], $sorting), true, true));
            $pager->setMaxPerPage(9);

            self::assertSame(12, $pager->getNbResults());
            $names = $this->names($pager->getCurrentPageResults());
            $pager->setCurrentPage(2);
            $names = [...$names, ...$this->names($pager->getCurrentPageResults())];

            self::assertCount(12, $names);
            self::assertCount(12, array_unique($names));
        }
    }

    public function test_negative_option_and_taxon_conditions_exclude_any_matching_value(): void
    {
        $channel = $this->catalog->channel('web');
        $sale = $this->catalog->taxon('Sale');
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $both = $this->catalog->product('Both sizes', [$channel], $sale);
        $this->catalog->variant($both, $channel, 1000, [$sizes['S']]);
        $this->catalog->variant($both, $channel, 1000, [$sizes['M']]);
        $medium = $this->catalog->product('Medium only', [$channel]);
        $this->catalog->variant($medium, $channel, 1000, [$sizes['M']]);

        $sizeCode = (string) $sizes['S']->getOption()?->getCode();

        self::assertSame(['Medium only'], $this->matchingNames([$this->condition(FacetCondition::TYPE_OPTION, 'not_in', $sizeCode, 'S')], [$both, $medium]));
        self::assertSame(['Medium only'], $this->matchingNames([$this->condition(FacetCondition::TYPE_TAXON, 'not_in', (string) $sale->getCode())], [$both, $medium]));
    }

    public function test_taxon_and_price_facets_follow_the_other_filters(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $parent = $this->catalog->taxon('Clothing');
        $parent->setIncludeChildrenProducts(true);
        $shirts = $this->catalog->taxon('Shirts', $parent);
        $hats = $this->catalog->taxon('Hats', $parent);
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $shirt = $this->catalog->product('Shirt', [$channel], $shirts);
        $this->catalog->variant($shirt, $channel, 2000, [$sizes['S']]);
        $hat = $this->catalog->product('Hat', [$channel], $hats);
        $this->catalog->variant($hat, $channel, 5000, [$sizes['M']]);

        $sizeCode = (string) $sizes['S']->getOption()?->getCode();
        $data = $this->queryBuilder()->getAdvancedFiltersData($parent, 'en_US', true, ['options' => [$sizeCode => ['S']]]);

        self::assertSame([(string) $shirts->getCode() => 1], array_column($data['facets']['taxons'], 'count', 'code'));
        self::assertSame(['min' => 20.0, 'max' => 20.0], ['min' => $data['facets']['price']['min'], 'max' => $data['facets']['price']['max']]);
    }

    public function test_facets_count_the_products_the_grid_search_keeps(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $sizes = $this->catalog->option('size', 'Size', ['S']);
        foreach (['Blue shirt', 'Blue polo', 'Red shirt'] as $name) {
            $product = $this->catalog->product($name, [$channel], $taxon);
            $this->catalog->variant($product, $channel, 1000, [$sizes['S']]);
        }

        $facets = $this->queryBuilder()->getAdvancedFiltersData($taxon, 'en_US', false, ['search' => 'Blue'])['facets']['options'];

        self::assertSame(2, $facets[0]['values'][0]['count']);
    }

    public function test_a_negative_condition_on_a_reference_that_does_not_exist_matches_nothing(): void
    {
        $channel = $this->catalog->channel('web');
        $product = $this->catalog->product('Anything', [$channel]);

        foreach ([FacetCondition::TYPE_ATTRIBUTE => 'not_equals', FacetCondition::TYPE_OPTION => 'not_in', FacetCondition::TYPE_TAXON => 'not_in'] as $type => $operator) {
            self::assertSame([], $this->matchingNames([$this->condition($type, $operator, 'deleted_reference', 'x')], [$product]));
        }
    }

    public function test_name_conditions_compare_the_name_in_the_given_locale(): void
    {
        $channel = $this->catalog->channel('web');
        $among = [
            $this->catalog->product('Steel watch', [$channel]),
            $this->catalog->product('Gold watch', [$channel]),
            $this->catalog->product('Steel ring', [$channel]),
        ];

        foreach ([
            ['equals', 'steel WATCH', ['Steel watch']],
            ['not_equals', 'Steel watch', ['Gold watch', 'Steel ring']],
            ['contains', 'steel', ['Steel ring', 'Steel watch']],
            ['not_contains', 'steel', ['Gold watch']],
        ] as [$operator, $value, $expected]) {
            self::assertSame($expected, $this->matchingNames([$this->condition(FacetCondition::TYPE_NAME, $operator, null, $value)], $among), $operator);
        }
    }

    public function test_description_conditions_keep_the_products_without_description_on_negative_operators(): void
    {
        $channel = $this->catalog->channel('web');
        $waterproof = $this->catalog->product('Diver', [$channel]);
        $waterproof->setDescription('Waterproof to 200 metres.');
        $plain = $this->catalog->product('Dress', [$channel]);
        $plain->setDescription('A thin dress watch.');
        $bare = $this->catalog->product('Bare', [$channel]);
        $this->entityManager->flush();
        $among = [$waterproof, $plain, $bare];

        self::assertSame(['Diver'], $this->matchingNames([$this->condition(FacetCondition::TYPE_DESCRIPTION, 'contains', null, 'waterproof')], $among));
        self::assertSame(['Bare', 'Dress'], $this->matchingNames([$this->condition(FacetCondition::TYPE_DESCRIPTION, 'not_contains', null, 'waterproof')], $among));
    }

    public function test_attribute_conditions_on_a_text_attribute(): void
    {
        $channel = $this->catalog->channel('web');
        $material = $this->catalog->attribute('material', 'text', 'Material');
        $steel = $this->catalog->product('Steel strap', [$channel]);
        $this->catalog->attributeValue($steel, $material, 'Brushed steel', 'en_US');
        $leather = $this->catalog->product('Leather strap', [$channel]);
        $this->catalog->attributeValue($leather, $material, 'Leather', 'en_US');
        $none = $this->catalog->product('No material', [$channel]);
        $among = [$steel, $leather, $none];
        $code = (string) $material->getCode();

        self::assertSame(['Steel strap'], $this->matchingNames([$this->condition(FacetCondition::TYPE_ATTRIBUTE, 'contains', $code, 'STEEL')], $among));
        self::assertSame(['Leather strap', 'No material'], $this->matchingNames([$this->condition(FacetCondition::TYPE_ATTRIBUTE, 'not_contains', $code, 'steel')], $among));
        self::assertSame(['Leather strap'], $this->matchingNames([$this->condition(FacetCondition::TYPE_ATTRIBUTE, 'equals', $code, 'Leather')], $among));
    }

    public function test_positive_option_and_taxon_conditions_keep_any_matching_value(): void
    {
        $channel = $this->catalog->channel('web');
        $sale = $this->catalog->taxon('Sale');
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $both = $this->catalog->product('Both sizes', [$channel], $sale);
        $this->catalog->variant($both, $channel, 1000, [$sizes['S']]);
        $this->catalog->variant($both, $channel, 1000, [$sizes['M']]);
        $medium = $this->catalog->product('Medium only', [$channel]);
        $this->catalog->variant($medium, $channel, 1000, [$sizes['M']]);

        $sizeCode = (string) $sizes['S']->getOption()?->getCode();

        self::assertSame(['Both sizes'], $this->matchingNames([$this->condition(FacetCondition::TYPE_OPTION, 'in', $sizeCode, 's')], [$both, $medium]));
        self::assertSame(['Both sizes', 'Medium only'], $this->matchingNames([$this->condition(FacetCondition::TYPE_OPTION, 'in', $sizeCode, 'M')], [$both, $medium]));
        self::assertSame(['Both sizes'], $this->matchingNames([$this->condition(FacetCondition::TYPE_TAXON, 'in', (string) $sale->getCode())], [$both, $medium]));
    }

    public function test_an_operator_stored_for_another_type_matches_nothing(): void
    {
        $channel = $this->catalog->channel('web');
        $product = $this->catalog->product('Steel watch', [$channel]);

        self::assertSame([], $this->matchingNames([$this->condition(FacetCondition::TYPE_NAME, 'not_in', null, 'Steel')], [$product]));
        self::assertSame([], $this->matchingNames([$this->condition(FacetCondition::TYPE_STOCK, 'not_contains', null, 'x')], [$product]));
    }

    public function test_conditions_are_combined_with_and(): void
    {
        $channel = $this->catalog->channel('web');
        $among = [
            $this->catalog->product('Steel watch', [$channel]),
            $this->catalog->product('Gold watch', [$channel]),
            $this->catalog->product('Steel ring', [$channel]),
        ];

        self::assertSame(['Steel watch'], $this->matchingNames([
            $this->condition(FacetCondition::TYPE_NAME, 'contains', null, 'steel'),
            $this->condition(FacetCondition::TYPE_NAME, 'contains', null, 'watch'),
        ], $among));
    }

    public function test_the_grid_ignores_the_filters_of_a_taxon_that_does_not_enable_them(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $small = $this->catalog->product('Small shirt', [$channel], $taxon);
        $this->catalog->variant($small, $channel, 1000, [$sizes['S']]);
        $medium = $this->catalog->product('Medium shirt', [$channel], $taxon);
        $this->catalog->variant($medium, $channel, 1000, [$sizes['M']]);

        $filters = ['options' => [(string) $sizes['S']->getOption()?->getCode() => ['S']], 'price' => ['min' => '50']];

        self::assertSame(['Medium shirt', 'Small shirt'], $this->names($this->listQuery($taxon, $filters, filtersEnabled: false)->getQuery()->getResult()));
    }

    public function test_the_filters_read_from_the_query_string_are_normalized(): void
    {
        $this->currentChannel = $this->catalog->channel('web');
        $taxon = $this->catalog->taxon('Shirts');

        $selected = $this->queryBuilder()->getAdvancedFiltersData($taxon, 'en_US', false, [
            'attributes' => ['material' => [' cotton ', 'cotton', '', ['nested']], '' => ['x'], 'empty' => ['  ']],
            'options' => ['size' => ['M', 3], 'color' => 'red'],
            'taxons' => ['12', 12, -4, 'abc', ['7']],
            'price' => ['min' => '80.5', 'max' => '-10'],
        ])['selected'];

        self::assertSame(['material' => ['cotton']], $selected['attributes']);
        self::assertSame(['size' => ['M']], $selected['options']);
        self::assertSame([12], $selected['taxons']);
        // Negative prices become 0, an inverted range is put back in order, amounts are in cents.
        self::assertSame(['min' => 0, 'max' => 8050], $selected['price']);
    }

    public function test_a_condition_on_an_attribute_that_is_neither_text_nor_select_matches_nothing(): void
    {
        $channel = $this->catalog->channel('web');
        $weight = $this->catalog->attribute('weight', 'integer', 'Weight');
        $heavy = $this->catalog->product('Heavy', [$channel]);
        $this->catalog->attributeValue($heavy, $weight, 5);
        $light = $this->catalog->product('Light', [$channel]);
        $this->catalog->attributeValue($light, $weight, 1);

        // Conditions only read text values: on an integer attribute, a negation would match all.
        self::assertSame([], $this->matchingNames([$this->condition(FacetCondition::TYPE_ATTRIBUTE, 'not_equals', (string) $weight->getCode(), '5')], [$heavy, $light]));
    }

    public function test_a_taxon_conditioned_on_its_own_membership_matches_nothing(): void
    {
        $channel = $this->catalog->channel('web');
        $product = $this->catalog->product('Anything', [$channel]);
        $taxon = $this->catalog->taxon('Self');
        $taxon->addFacetCondition($this->condition(FacetCondition::TYPE_TAXON, 'not_in', (string) $taxon->getCode()));
        $this->entityManager->flush();

        $qb = $this->conditionQueryBuilder()->buildQuery($taxon, 'en_US');
        $qb->andWhere('p IN (:among)')->setParameter('among', [$product]);

        self::assertSame([], $this->names($qb->getQuery()->getResult()));
    }

    public function test_the_filters_of_a_request_are_capped(): void
    {
        $this->currentChannel = $this->catalog->channel('web');
        $taxon = $this->catalog->taxon('Shirts');

        $attributes = [];
        for ($i = 0; $i < 30; ++$i) {
            $attributes['attribute_' . $i] = array_map(static fn (int $value): string => 'value ' . $value, range(1, 80));
        }

        $selected = $this->queryBuilder()->getAdvancedFiltersData($taxon, 'en_US', false, [
            'attributes' => $attributes,
            'options' => ['size' => ['S']],
            'taxons' => range(1, 80),
        ])['selected'];
        self::assertIsArray($selected['attributes']);
        self::assertIsArray($selected['attributes']['attribute_0']);
        self::assertIsArray($selected['taxons']);

        self::assertCount(FacetProductQueryBuilder::MAX_FILTER_CODES, $selected['attributes']);
        self::assertSame([], $selected['options'], 'The codes allowed are shared by attributes and options.');
        self::assertCount(FacetProductQueryBuilder::MAX_FILTER_VALUES, $selected['attributes']['attribute_0']);
        self::assertCount(FacetProductQueryBuilder::MAX_FILTER_VALUES, $selected['taxons']);
    }

    public function test_the_sub_taxon_facet_leaves_out_disabled_taxons(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $parent = $this->catalog->taxon('Clothing');
        $shirts = $this->catalog->taxon('Shirts', $parent);
        $hidden = $this->catalog->taxon('Hidden sale', $parent);
        $hidden->setEnabled(false);
        $shirt = $this->catalog->product('Shirt', [$channel], $shirts);
        $this->catalog->assign($shirt, $hidden);
        $this->entityManager->flush();

        $taxons = $this->queryBuilder()->getAdvancedFiltersData($parent, 'en_US', true, [])['facets']['taxons'];

        self::assertSame([(string) $shirts->getCode()], array_column($taxons, 'code'));
    }

    public function test_facets_leave_out_products_the_grid_does_not_list_in_this_locale(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $listed = $this->catalog->product('Listed', [$channel], $taxon);
        $this->catalog->variant($listed, $channel, 1000);
        $untranslated = $this->catalog->product('Untranslated', [$channel], $taxon);
        $this->catalog->variant($untranslated, $channel, 9000);

        // The grid joins the product translation of the shop locale: only Listed has a fr_FR one.
        $translationClass = $listed->getTranslation('en_US')::class;
        $french = new $translationClass();
        $french->setLocale('fr_FR');
        $french->setName('Liste');
        $french->setSlug($this->catalog->code('liste'));
        $listed->addTranslation($french);
        $this->entityManager->flush();

        $price = $this->queryBuilder()->getAdvancedFiltersData($taxon, 'fr_FR', false, [])['facets']['price'];

        self::assertSame(['min' => 10.0, 'max' => 10.0], ['min' => $price['min'], 'max' => $price['max']]);
    }

    public function test_a_text_value_stored_with_spaces_stays_filterable(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $color = $this->catalog->attribute('color', 'text', 'Color');
        $red = $this->catalog->product('Red shirt', [$channel], $taxon);
        $this->catalog->attributeValue($red, $color, 'Red ', 'en_US');

        self::assertSame(['Red shirt'], $this->filteredNames($taxon, ['attributes' => [(string) $color->getCode() => ['Red']]]));
    }

    public function test_text_values_differing_only_by_spaces_are_one_facet_value(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Shirts');
        $color = $this->catalog->attribute('color', 'text', 'Color');
        $this->catalog->attributeValue($this->catalog->product('Red shirt', [$channel], $taxon), $color, 'Red', 'en_US');
        $this->catalog->attributeValue($this->catalog->product('Red polo', [$channel], $taxon), $color, 'Red ', 'en_US');

        $values = $this->facets($taxon, [])['attributes'][0]['values'];

        self::assertSame([['Red', 2]], array_map(static fn (array $value): array => [$value['value'], $value['count']], $values));
    }

    public function test_a_selected_facet_keeps_offering_its_other_values_and_marks_the_selected_one(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $taxon = $this->catalog->taxon('Straps');
        $material = $this->catalog->attribute('material', 'text', 'Material');
        $steel = $this->catalog->product('Steel strap', [$channel], $taxon);
        $this->catalog->attributeValue($steel, $material, 'Steel', 'en_US');
        $gold = $this->catalog->product('Gold strap', [$channel], $taxon);
        $this->catalog->attributeValue($gold, $material, 'Gold', 'en_US');

        $facets = $this->facets($taxon, ['attributes' => [(string) $material->getCode() => ['Steel']]])['attributes'];

        self::assertSame(
            ['Gold' => [1, false], 'Steel' => [1, true]],
            array_combine(
                array_column($facets[0]['values'], 'value'),
                array_map(static fn (array $value): array => [$value['count'], $value['active']], $facets[0]['values']),
            ),
        );
    }

    public function test_the_grid_filters_on_a_sub_taxon(): void
    {
        $channel = $this->catalog->channel('web');
        $this->currentChannel = $channel;
        $parent = $this->catalog->taxon('Clothing');
        $shirts = $this->catalog->taxon('Shirts', $parent);
        $hats = $this->catalog->taxon('Hats', $parent);
        $this->catalog->product('Shirt', [$channel], $shirts);
        $this->catalog->product('Hat', [$channel], $hats);

        self::assertSame(['Shirt'], $this->names($this->listQuery($parent, ['taxons' => [$shirts->getId()]], [], true, true)->getQuery()->getResult()));
    }

    public function test_facets_and_prices_only_count_enabled_products_and_variants_of_the_current_channel(): void
    {
        $web = $this->catalog->channel('web');
        $other = $this->catalog->channel('other');
        $this->currentChannel = $web;
        $taxon = $this->catalog->taxon('Shirts');
        $sizes = $this->catalog->option('size', 'Size', ['S', 'M']);
        $listed = $this->catalog->product('Listed', [$web, $other], $taxon);
        $this->catalog->variant($listed, $web, 2000, [$sizes['S']]);
        $this->catalog->variant($listed, $other, 100, [$sizes['S']]);
        $disabledVariant = $this->catalog->variant($listed, $web, 9000, [$sizes['M']]);
        $disabledVariant->setEnabled(false);
        $disabled = $this->catalog->product('Disabled', [$web], $taxon);
        $this->catalog->variant($disabled, $web, 9500, [$sizes['M']]);
        $disabled->setEnabled(false);
        $elsewhere = $this->catalog->product('Elsewhere', [$other], $taxon);
        $this->catalog->variant($elsewhere, $other, 50, [$sizes['M']]);
        $this->entityManager->flush();

        $data = $this->queryBuilder()->getAdvancedFiltersData($taxon, 'en_US', false, []);

        self::assertSame(['S' => 1], array_column($data['facets']['options'][0]['values'], 'count', 'value'));
        self::assertSame(['min' => 20.0, 'max' => 20.0], ['min' => $data['facets']['price']['min'], 'max' => $data['facets']['price']['max']]);
    }

    public function test_the_stock_condition_keeps_a_tracked_product_with_stock_left_on_an_enabled_variant(): void
    {
        $channel = $this->catalog->channel('web');
        $inStock = $this->catalog->product('In stock', [$channel]);
        $this->catalog->variant($inStock, $channel, 1000, onHand: 3, onHold: 1);
        $disabledStock = $this->catalog->product('Disabled stock', [$channel]);
        $this->catalog->variant($disabledStock, $channel, 1000, onHand: 0);
        $hidden = $this->catalog->variant($disabledStock, $channel, 1000, onHand: 5);
        $hidden->setEnabled(false);
        $this->entityManager->flush();

        self::assertSame(['In stock'], $this->matchingNames([$this->condition(FacetCondition::TYPE_STOCK, 'is_in_stock')], [$inStock, $disabledStock]));
    }

    /**
     * @param list<FacetCondition> $conditions
     * @param list<ProductInterface> $among products the assertion is restricted to, the test
     *                                      database possibly holding other ones
     *
     * @return list<string>
     */
    private function matchingNames(array $conditions, array $among): array
    {
        $taxon = $this->catalog->taxon(uniqid('Conditional ', false));
        foreach ($conditions as $condition) {
            $taxon->addFacetCondition($condition);
        }

        $qb = $this->conditionQueryBuilder()->buildQuery($taxon, 'en_US');
        $qb->andWhere('p IN (:among)')->setParameter('among', $among);

        return $this->names($qb->getQuery()->getResult());
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<string>
     */
    private function filteredNames(AdvancedTaxonInterface $taxon, array $filters): array
    {
        return $this->names($this->listQuery($taxon, $filters)->getQuery()->getResult());
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, string> $sorting
     */
    private function listQuery(AdvancedTaxonInterface $taxon, array $filters, array $sorting = [], bool $filtersEnabled = true, bool $includeAllDescendants = false): QueryBuilder
    {
        $taxon->setAdvancedFiltersEnabled($filtersEnabled);

        /** @var ProductRepositoryInterface<ProductInterface> $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');
        Assert::notNull($this->currentChannel);
        Assert::isInstanceOf($this->currentChannel, CoreChannelInterface::class);

        $listQueryBuilder = new ShopProductListQueryBuilder($productRepository, $this->queryBuilder());

        return $listQueryBuilder->createListQueryBuilder($this->currentChannel, $taxon, 'en_US', $sorting, $includeAllDescendants, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     attributes: array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>,
     *     options: array<int, array{code: string, label: string, values: array<int, array{value: string, label: string, count: int, active: bool}>}>
     * }
     */
    private function facets(AdvancedTaxonInterface $taxon, array $filters): array
    {
        return $this->queryBuilder()->getAdvancedFiltersData($taxon, 'en_US', false, $filters)['facets'];
    }

    /**
     * @return list<string>
     */
    private function names(mixed $products): array
    {
        Assert::isIterable($products);

        $names = [];
        foreach ($products as $product) {
            Assert::isInstanceOf($product, ProductInterface::class);
            $names[] = (string) $product->getName();
        }
        sort($names);

        return $names;
    }

    private function condition(string $type, string $operator, ?string $reference = null, ?string $value = null): FacetCondition
    {
        $condition = new FacetCondition();
        $condition->setConditionType($type);
        $condition->setOperator($operator);
        $condition->setReferenceCode($reference);
        $condition->setValue($value);

        return $condition;
    }

    private function conditionQueryBuilder(): ConditionalTaxonQueryBuilder
    {
        /** @var class-string<ProductInterface> $productClass */
        $productClass = self::getContainer()->getParameter('sylius.model.product.class');
        $referenceChecker = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.facet.reference_checker');
        Assert::isInstanceOf($referenceChecker, FacetReferenceCheckerInterface::class);

        return new ConditionalTaxonQueryBuilder($this->entityManager, $productClass, $referenceChecker, $this->selectAttributeValues());
    }

    private function selectAttributeValues(): SelectAttributeValueResolver
    {
        $attributeValueClass = self::getContainer()->getParameter('sylius.model.product_attribute_value.class');
        Assert::string($attributeValueClass);
        Assert::classExists($attributeValueClass);

        return new SelectAttributeValueResolver($this->entityManager, $attributeValueClass);
    }

    private function queryBuilder(): FacetProductQueryBuilder
    {
        $channelContext = new class($this->currentChannel) implements ChannelContextInterface {
            public function __construct(private readonly ?ChannelInterface $channel)
            {
            }

            public function getChannel(): ChannelInterface
            {
                return $this->channel ?? throw new ChannelNotFoundException();
            }
        };

        /** @var class-string<ProductInterface> $productClass */
        $productClass = self::getContainer()->getParameter('sylius.model.product.class');

        return new FacetProductQueryBuilder($this->entityManager, $productClass, $channelContext, $this->selectAttributeValues());
    }
}
