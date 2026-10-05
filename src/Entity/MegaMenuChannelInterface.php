<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Sylius\Component\Core\Model\ChannelInterface;

interface MegaMenuChannelInterface extends ChannelInterface
{
    public function hasMegaMenu(): bool;

    public function setHasMegaMenu(bool $hasMegaMenu): void;
}
