<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Shop;

use FriendsOfBehat\PageObjectExtension\Element\Element;
use Webmozart\Assert\Assert;

/**
 * The shop main menu, as the plugin renders it: the mega menu when the channel enables it, the
 * Sylius menu otherwise.
 */
final class MenuElement extends Element
{
    private const BROWSER_TIMEOUT_MS = 5000;

    /** @var array{0: int, 1: int}|null */
    private ?array $desktopSize = null;

    private const OPEN_DRAWER = 'document.querySelector("#at-mn-drawer")?.classList.contains("at-mn-drawer--open")';

    public function isMegaMenuDisplayed(): bool
    {
        return $this->hasElement('mega_menu');
    }

    public function hasMobileMenuButton(): bool
    {
        return $this->hasElement('mobile_menu_button');
    }

    /**
     * Below the large breakpoint the menu is the burger and its drawer.
     */
    public function useSmallScreen(): void
    {
        /** @var array{0: int, 1: int} $size */
        $size = $this->getSession()->evaluateScript('[window.outerWidth, window.outerHeight]');
        $this->desktopSize = $size;
        $this->getSession()->resizeWindow(390, 844);
    }

    public function restoreScreen(): void
    {
        if ($this->desktopSize !== null) {
            $this->getSession()->resizeWindow(...$this->desktopSize);
            $this->desktopSize = null;
        }
    }

    public function openMobileMenu(): void
    {
        // A real click: the drawer has to open from the burger itself.
        $this->getElement('mobile_menu_button')->click();
        $this->waitFor(self::OPEN_DRAWER);
    }

    public function openTaxonInMobileMenu(string $taxon): void
    {
        $this->getElement('mobile_menu_active_screen')->pressButton($taxon);
        $this->waitFor(sprintf(
            'document.querySelector(%s)?.textContent.includes(%s)',
            json_encode('#at-mn-drawer [data-mn-state="active"] .at-mn-screen-title'),
            json_encode($taxon),
        ));
    }

    public function isTaxonLinkedInMobileMenu(string $taxon): bool
    {
        return $this->getElement('mobile_menu_active_screen')->hasLink($taxon);
    }

    public function closeMobileMenuWithEscape(): void
    {
        $this->getSession()->executeScript('document.dispatchEvent(new KeyboardEvent("keydown", {key: "Escape", bubbles: true}))');
        $this->waitFor('!' . self::OPEN_DRAWER);
    }

    public function isMobileMenuOpen(): bool
    {
        return (bool) $this->getSession()->evaluateScript(self::OPEN_DRAWER);
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
            'mobile_menu_active_screen' => '#at-mn-drawer [data-mn-state="active"]',
            'mobile_menu_button' => '[data-test-mobile-menu-button]',
        ];
    }

    private function waitFor(string $condition): void
    {
        Assert::true($this->getSession()->wait(self::BROWSER_TIMEOUT_MS, $condition), sprintf('Timed out waiting for: %s', $condition));
    }
}
