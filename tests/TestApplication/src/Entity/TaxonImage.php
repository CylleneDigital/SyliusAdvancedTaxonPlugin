<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\TaxonImage as BaseTaxonImage;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon_image')]
class TaxonImage extends BaseTaxonImage implements AdvancedTaxonImageInterface
{
    use AdvancedTaxonImageTrait;

    public function __construct()
    {
        $this->initializeAdvancedTaxonImage();
    }
}
