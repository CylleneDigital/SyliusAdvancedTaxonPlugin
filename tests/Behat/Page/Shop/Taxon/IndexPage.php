<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Page\Shop\Taxon;

use Behat\Mink\Element\NodeElement;
use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;
use Webmozart\Assert\Assert;

/**
 * A taxon page of the shop: the product listing of a taxon, or its universe page.
 */
final class IndexPage extends SymfonyPage
{
    public function getRouteName(): string
    {
        return 'sylius_shop_product_index';
    }

    /**
     * @return list<string>
     */
    public function getFeaturedChildTaxons(): array
    {
        return $this->getAttributeValues('featured_children', '[data-test-universe-child-top-link]', 'data-test-universe-child-top-link');
    }

    /**
     * @return list<string>
     */
    public function getFeaturedProductCodes(): array
    {
        return $this->getAttributeValues('featured_products', '[data-test-product]', 'data-test-product');
    }

    public function isProductListed(string $code): bool
    {
        return $this->getDocument()->find('css', sprintf('[data-test-products] [data-test-product="%1$s"], [data-test-products-list] [data-test-product-list-item="%1$s"]', $code)) !== null;
    }

    public function hasProductList(): bool
    {
        return $this->hasElement('products') || $this->hasElement('products_list');
    }

    public function hasMedia(string $zone, string $title): bool
    {
        return $this->hasElement('media', ['%zone%' => $zone, '%title%' => $title]);
    }

    public function isZoneSlider(string $zone): bool
    {
        return $this->hasElement('media_zone_slider', ['%zone%' => $zone]);
    }

    public function hasMediaCard(string $title): bool
    {
        return $this->hasElement('media_card', ['%title%' => $title]);
    }

    public function hasMediaListItem(string $title): bool
    {
        return $this->hasElement('media_list_item', ['%title%' => $title]);
    }

    /**
     * @return list<string> "media:<title>" for a media card, "product" for a product card
     */
    public function getProductListItems(): array
    {
        return array_values(array_map(
            static fn (NodeElement $item): string => $item->hasAttribute('data-test-media-product-card') ? 'media:' . $item->getAttribute('data-test-media-product-card') : 'product',
            $this->getElement('products')->findAll('xpath', '/*'),
        ));
    }

    public function hasMainImage(): bool
    {
        return $this->hasElement('main_image');
    }

    public function getTaxonNameColor(): ?string
    {
        $style = $this->getElement('taxon_name')->find('css', 'span[style]')?->getAttribute('style');

        return $style !== null && preg_match('/color:\s*(#[0-9a-fA-F]{6})/', $style, $matches) === 1 ? strtolower($matches[1]) : null;
    }

    public function hasTaxonNameIcon(): bool
    {
        return $this->getElement('taxon_name')->find('css', 'svg') !== null;
    }

    public function hasAdvancedFilters(): bool
    {
        return $this->hasElement('advanced_filters');
    }

    public function hasAllFiltersButton(): bool
    {
        return $this->hasElement('all_filters_button');
    }

    public function getFilterValueCount(string $filter, string $value): ?int
    {
        if (!$this->hasElement('facet_value', ['%filter%' => $filter, '%value%' => $value])) {
            return null;
        }

        $count = $this->getElement('facet_value', ['%filter%' => $filter, '%value%' => $value])->find('css', '[data-test-facet-value-count]');

        return $count === null ? null : (int) $count->getText();
    }

    public function filterBy(string $filter, string $value): void
    {
        $checkbox = $this->getElement('facet_value', ['%filter%' => $filter, '%value%' => $value])->find('css', 'input[type="checkbox"]');
        Assert::notNull($checkbox, sprintf('The "%s" value of the "%s" filter has no checkbox.', $value, $filter));
        $checkbox->check();

        $form = $this->getElement('advanced_filters')->find('css', 'form');
        Assert::notNull($form, 'The advanced filters have no form.');
        $form->submit();
    }

    public function searchFor(string $text): void
    {
        $field = $this->getElement('grid_search_field');
        $field->setValue($text);
        $form = $field->find('xpath', 'ancestor::form');
        Assert::notNull($form, 'The search field of the product list has no form.');
        $form->submit();
    }

    public function clearSearch(): void
    {
        $this->getElement('grid_search_clear')->click();
    }

    public function getUniverseTitle(): string
    {
        return $this->getElement('universe_title')->getText();
    }

    /**
     * @return list<string>
     */
    public function getUniverseChildTaxons(): array
    {
        return $this->getAttributeValues('universe_page', '[data-test-universe-child-top-link]', 'data-test-universe-child-top-link');
    }

    /**
     * @return list<string>
     */
    public function getUniverseFeaturedProductCodes(string $child): array
    {
        return array_values(array_map(
            static fn (NodeElement $product): string => (string) $product->getAttribute('data-test-product'),
            $this->getElement('universe_child_block', ['%child%' => $child])->findAll('css', '[data-test-product]'),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function getDefinedElements(): array
    {
        return [
            'advanced_filters' => '[data-test-advanced-filters-sidebar]',
            'all_filters_button' => '[data-test-advanced-filters-toggle]',
            'facet_value' => '[data-test-facet="%filter%"] [data-test-facet-value="%value%"]',
            'featured_children' => '[data-test-featured-children]',
            'featured_products' => '[data-test-featured-products-position]',
            'grid_search_clear' => '[data-test-clear]',
            'grid_search_field' => 'input[name="criteria[search][value]"]',
            'main_image' => '[data-test-taxon-main-image]',
            'media' => '[data-test-media-zone="%zone%"] [data-test-media="%title%"]',
            'media_card' => '[data-test-media-product-card="%title%"]',
            'media_zone_slider' => '[data-test-media-zone="%zone%"][data-controller="at-universe-slider"]',
            'media_list_item' => '[data-test-products-list] [data-test-media-product-list-item="%title%"]',
            'products' => '[data-test-products]',
            'products_list' => '[data-test-products-list]',
            'taxon_name' => '[data-test-taxon-name]',
            'universe_child_block' => '[data-test-universe-child-block="%child%"]',
            'universe_page' => '[data-test-universe-page]',
            'universe_title' => '[data-test-universe-title]',
        ];
    }

    /**
     * @return list<string>
     */
    private function getAttributeValues(string $container, string $selector, string $attribute): array
    {
        if (!$this->hasElement($container)) {
            return [];
        }

        $values = [];
        foreach ($this->getElement($container)->findAll('css', $selector) as $node) {
            $value = $node->getAttribute($attribute);
            Assert::notNull($value);
            $values[] = $value;
        }

        return $values;
    }
}
