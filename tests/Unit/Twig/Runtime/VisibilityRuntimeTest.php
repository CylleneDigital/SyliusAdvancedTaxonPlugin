<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\VisibilityRuntime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

final class VisibilityRuntimeTest extends TestCase
{
    public function test_it_keeps_the_enabled_products_of_the_current_channel(): void
    {
        $web = new Channel();
        $b2b = new Channel();

        $visible = $this->product('Visible', true, $web);
        $disabled = $this->product('Disabled', false, $web);
        $otherChannel = $this->product('Other channel', true, $b2b);

        self::assertSame([$visible], $this->runtime($web)->visibleProducts([$visible, $disabled, $otherChannel, 'not a product']));
    }

    public function test_it_keeps_the_enabled_taxons(): void
    {
        $enabled = new Taxon();
        $disabled = new Taxon();
        $disabled->setEnabled(false);

        self::assertSame([$enabled], $this->runtime(new Channel())->visibleTaxons([$enabled, $disabled]));
    }

    private function product(string $code, bool $enabled, Channel $channel): Product
    {
        $product = new Product();
        $product->setCode($code);
        $product->setEnabled($enabled);
        $product->addChannel($channel);

        return $product;
    }

    private function runtime(ChannelInterface $channel): VisibilityRuntime
    {
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        // Products built in memory have their channels loaded: the database is never asked.
        return new VisibilityRuntime($channelContext, $this->createStub(EntityManagerInterface::class), Product::class);
    }
}
