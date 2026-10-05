<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Channel as BaseChannel;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_channel')]
class Channel extends BaseChannel implements MegaMenuChannelInterface
{
    use MegaMenuChannelTrait;
}
