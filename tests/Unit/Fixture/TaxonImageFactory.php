<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Fixture;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;

/**
 * @implements FactoryInterface<AdvancedTaxonImageInterface>
 */
final class TaxonImageFactory implements FactoryInterface
{
    public function createNew(): AdvancedTaxonImageInterface
    {
        return new TaxonImage();
    }
}
