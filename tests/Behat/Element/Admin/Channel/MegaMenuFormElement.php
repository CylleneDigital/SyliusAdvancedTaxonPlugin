<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Channel;

use FriendsOfBehat\PageObjectExtension\Element\Element;

/**
 * The mega menu toggle the plugin adds to the admin channel form.
 */
final class MegaMenuFormElement extends Element
{
    public function enableMegaMenu(): void
    {
        $this->getDocument()->checkField('sylius_admin_channel[hasMegaMenu]');
    }
}
