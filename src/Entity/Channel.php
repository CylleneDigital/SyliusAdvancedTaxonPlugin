<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Channel as BaseChannel;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_channel')]
class Channel extends BaseChannel
{
    #[ORM\Column(name: 'has_mega_menu', type: 'boolean', options: ['default' => false])]
    private bool $hasMegaMenu = false;

    public function hasMegaMenu(): bool
    {
        return $this->hasMegaMenu;
    }

    public function isHasMegaMenu(): bool
    {
        return $this->hasMegaMenu;
    }

    public function setHasMegaMenu(bool $hasMegaMenu): void
    {
        $this->hasMegaMenu = $hasMegaMenu;
    }
}
