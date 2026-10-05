<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use FriendsOfBehat\PageObjectExtension\Page\PageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Shop\MenuElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Page\Shop\Taxon\IndexPage;
use Webmozart\Assert\Assert;

/**
 * What a visitor sees of the plugin in the shop: taxon and universe pages, filters and menus.
 */
final class BrowsingAdvancedTaxonContext implements Context
{
    public function __construct(
        private readonly IndexPage $taxonPage,
        private readonly PageInterface $homePage,
        private readonly MenuElement $menu,
    ) {
    }

    #[When('/^I browse the ("[^"]+" taxon)$/')]
    public function iBrowseTheTaxon(TaxonInterface $taxon): void
    {
        $this->taxonPage->open(['slug' => $taxon->getTranslation('en_US')->getSlug(), '_locale' => 'en_US']);
    }

    #[When('/^I browse the ("[^"]+" taxon) in the list view$/')]
    public function iBrowseTheTaxonInTheListView(TaxonInterface $taxon): void
    {
        $this->taxonPage->open(['slug' => $taxon->getTranslation('en_US')->getSlug(), '_locale' => 'en_US', 'view' => 'list']);
    }

    #[When('I open the shop homepage')]
    public function iOpenTheShopHomepage(): void
    {
        $this->homePage->open(['_locale' => 'en_US']);
    }

    #[When('I filter the products by :value in the :filter filter')]
    public function iFilterTheProductsBy(string $value, string $filter): void
    {
        $this->taxonPage->filterBy($filter, $value);
    }

    #[When('I search for :text in the product list')]
    public function iSearchForInTheProductList(string $text): void
    {
        $this->taxonPage->searchFor($text);
    }

    #[When('I clear the product search')]
    public function iClearTheProductSearch(): void
    {
        $this->taxonPage->clearSearch();
    }

    #[Then('the featured child taxon should be :name')]
    public function theFeaturedChildTaxonShouldBe(string $name): void
    {
        Assert::same($this->taxonPage->getFeaturedChildTaxons(), [$name]);
    }

    #[Then('I should not see any featured child taxon')]
    public function iShouldNotSeeAnyFeaturedChildTaxon(): void
    {
        Assert::isEmpty($this->taxonPage->getFeaturedChildTaxons());
    }

    #[Then('/^the featured products should be the ("[^"]+" product)$/')]
    public function theFeaturedProductsShouldBe(ProductInterface $product): void
    {
        Assert::same($this->taxonPage->getFeaturedProductCodes(), [$product->getCode()]);
    }

    #[Then('I should not see any featured product')]
    public function iShouldNotSeeAnyFeaturedProduct(): void
    {
        Assert::isEmpty($this->taxonPage->getFeaturedProductCodes());
    }

    #[Then('/^I should (not )?see the ("[^"]+" product) in the product list$/')]
    public function iShouldSeeTheProductInTheProductList(string $not, ProductInterface $product): void
    {
        Assert::same(
            $this->taxonPage->isProductListed((string) $product->getCode()),
            $not === '',
            sprintf('The product "%s" %s be listed.', (string) $product->getName(), $not === '' ? 'should' : 'should not'),
        );
    }

    #[Then('I should see the media :title in the :zone zone')]
    public function iShouldSeeTheMediaInTheZone(string $title, string $zone): void
    {
        Assert::true($this->taxonPage->hasMedia($zone, $title), sprintf('The media "%s" is not shown in the "%s" zone.', $title, $zone));
    }

    #[Then('the :zone zone should be a slider of the media :first and :second')]
    public function theZoneShouldBeASliderOfTheMedia(string $zone, string $first, string $second): void
    {
        Assert::true($this->taxonPage->isZoneSlider($zone), sprintf('The "%s" zone is not a slider.', $zone));
        Assert::true($this->taxonPage->hasMedia($zone, $first));
        Assert::true($this->taxonPage->hasMedia($zone, $second));
    }

    #[Then('I should see the media card :title in the product list')]
    public function iShouldSeeTheMediaCardInTheProductList(string $title): void
    {
        Assert::true($this->taxonPage->hasMediaCard($title), sprintf('The media card "%s" is not shown.', $title));
    }

    #[Then('the media card :title should be the first item of the product list')]
    public function theMediaCardShouldBeTheFirstItem(string $title): void
    {
        Assert::same($this->taxonPage->getProductListItems()[0] ?? null, 'media:' . $title);
    }

    #[Then('the media card :title should be neither the first nor the last item of the product list')]
    public function theMediaCardShouldBeAmongTheProducts(string $title): void
    {
        $items = $this->taxonPage->getProductListItems();
        $position = array_search('media:' . $title, $items, true);

        Assert::integer($position, sprintf('The media card "%s" is not listed.', $title));
        Assert::notSame($position, 0);
        Assert::notSame($position, count($items) - 1);
    }

    #[Then('I should see the media :title among the products of the list view')]
    public function iShouldSeeTheMediaAmongTheProductsOfTheListView(string $title): void
    {
        Assert::true($this->taxonPage->hasMediaListItem($title), sprintf('The media "%s" is not listed.', $title));
    }

    #[Then('I should see the main image of the taxon')]
    public function iShouldSeeTheMainImageOfTheTaxon(): void
    {
        Assert::true($this->taxonPage->hasMainImage());
    }

    #[Then('the taxon name should be shown in :color with its icon')]
    public function theTaxonNameShouldBeShownInWithItsIcon(string $color): void
    {
        Assert::same($this->taxonPage->getTaxonNameColor(), strtolower($color));
        Assert::true($this->taxonPage->hasTaxonNameIcon(), 'The taxon name has no icon.');
    }

    #[Then('the taxon name should be shown without its color nor its icon')]
    public function theTaxonNameShouldBeShownWithoutItsColorNorItsIcon(): void
    {
        Assert::null($this->taxonPage->getTaxonNameColor());
        Assert::false($this->taxonPage->hasTaxonNameIcon(), 'The taxon name still has an icon.');
    }

    #[Then('/^I should (not )?see the advanced filters$/')]
    public function iShouldSeeTheAdvancedFilters(string $not = ''): void
    {
        Assert::same($this->taxonPage->hasAdvancedFilters(), $not === '');
        Assert::same($this->taxonPage->hasAllFiltersButton(), $not === '');
    }

    #[Then('the :filter filter should offer :value for :count product(s)')]
    public function theFilterShouldOfferForProducts(string $filter, string $value, int $count): void
    {
        Assert::same($this->taxonPage->getFilterValueCount($filter, $value), $count);
    }

    #[Then('the universe title should be :title')]
    public function theUniverseTitleShouldBe(string $title): void
    {
        Assert::same($this->taxonPage->getUniverseTitle(), $title);
    }

    #[Then('/^the universe should present the child taxons "([^"]+)" and "([^"]+)"$/')]
    public function theUniverseShouldPresentTheChildTaxons(string $first, string $second): void
    {
        Assert::same($this->taxonPage->getUniverseChildTaxons(), [$first, $second]);
    }

    #[Then('I should not see any product list')]
    public function iShouldNotSeeAnyProductList(): void
    {
        Assert::false($this->taxonPage->hasProductList());
    }

    #[Then('/^the universe should present the ("[^"]+" product) among the featured products of the ("[^"]+" taxon)$/')]
    public function theUniverseShouldPresentTheProductAmongTheFeaturedProductsOf(ProductInterface $product, TaxonInterface $child): void
    {
        Assert::same($this->taxonPage->getUniverseFeaturedProductCodes((string) $child->getName()), [$product->getCode()]);
    }

    #[Then('/^the mega menu should (not )?be displayed$/')]
    public function theMegaMenuShouldBeDisplayed(string $not = ''): void
    {
        Assert::same($this->menu->isMegaMenuDisplayed(), $not === '');
        Assert::same($this->menu->hasMobileMenuButton(), $not === '');
    }

    #[Then('/^the mega menu should feature the ("[^"]+" taxon) under the ("[^"]+" taxon)$/')]
    public function theMegaMenuShouldFeatureTheTaxonUnder(TaxonInterface $featuredTaxon, TaxonInterface $taxon): void
    {
        Assert::true($this->menu->isFeaturedInMegaMenu((string) $taxon->getName(), (string) $featuredTaxon->getName()));
    }

    #[Then('/^the ("[^"]+" taxon) should be shown in "([^"]+)" in the menu$/')]
    public function theTaxonShouldBeShownInInTheMenu(TaxonInterface $taxon, string $color): void
    {
        Assert::same($this->menu->getMenuItemColor((string) $taxon->getName()), strtolower($color));
    }

    #[Then('/^the ("[^"]+" taxon) should be shown without its color in the menu$/')]
    public function theTaxonShouldBeShownWithoutItsColorInTheMenu(TaxonInterface $taxon): void
    {
        Assert::null($this->menu->getMenuItemColor((string) $taxon->getName()));
    }
}
