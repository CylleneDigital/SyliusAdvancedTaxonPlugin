<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Shop;

use FriendsOfBehat\PageObjectExtension\Element\Element;

/**
 * The shop main menu, as the plugin renders it: the mega menu when the channel enables it, the
 * Sylius menu otherwise.
 */
final class MenuElement extends Element
{
    public function isMegaMenuDisplayed(): bool
    {
        return $this->hasElement('mega_menu');
    }

    public function hasMobileMenuButton(): bool
    {
        return $this->hasElement('mobile_menu_button');
    }

    public function isFeaturedInMegaMenu(string $taxon, string $featuredTaxon): bool
    {
        return $this->hasElement('mega_menu_featured_item', ['%taxon%' => $taxon, '%featured%' => $featuredTaxon]);
    }

    public function getMenuItemColor(string $taxon): ?string
    {
        $style = $this->getElement('menu_item', ['%taxon%' => $taxon])->find('css', 'a span[style]')?->getAttribute('style');

        return $style !== null && preg_match('/color:\s*(#[0-9a-fA-F]{6})/', $style, $matches) === 1 ? strtolower($matches[1]) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function getDefinedElements(): array
    {
        return [
            'mega_menu' => '[data-test-mega-menu]',
            'mega_menu_featured_item' => '[data-test-mega-menu-featured="%taxon%"] [data-test-menu-item="%featured%"]',
            'menu_item' => '[data-test-menu-item="%taxon%"]',
            'mobile_menu_button' => '[data-test-mobile-menu-button]',
        ];
    }
}
