<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-require-implements MegaMenuChannelInterface
 */
trait MegaMenuChannelTrait
{
    #[ORM\Column(name: 'has_mega_menu', type: 'boolean', options: ['default' => false])]
    private bool $hasMegaMenu = false;

    public function hasMegaMenu(): bool
    {
        return $this->hasMegaMenu;
    }

    public function setHasMegaMenu(bool $hasMegaMenu): void
    {
        $this->hasMegaMenu = $hasMegaMenu;
    }
}
