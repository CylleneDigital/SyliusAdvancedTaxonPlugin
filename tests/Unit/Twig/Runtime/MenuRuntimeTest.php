<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\MenuRuntime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Taxon as SyliusTaxon;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

final class MenuRuntimeTest extends TestCase
{
    public function test_the_featured_children_come_first_then_the_other_enabled_ones(): void
    {
        $parent = new Taxon();
        $first = $this->child($parent, 'first');
        $second = $this->child($parent, 'second');
        $third = $this->child($parent, 'third');
        $disabled = $this->child($parent, 'disabled');
        $disabled->setEnabled(false);
        $elsewhere = new Taxon();

        $parent->addFeaturedChild($third);
        $parent->addFeaturedChild($disabled);
        $parent->addFeaturedChild($elsewhere);

        self::assertSame([$third, $first, $second], $this->runtime()->getOrderedChildren($parent));
    }

    public function test_a_taxon_of_another_model_keeps_its_children_order(): void
    {
        $parent = new SyliusTaxon();
        $first = new SyliusTaxon();
        $second = new SyliusTaxon();
        $parent->addChild($first);
        $parent->addChild($second);

        self::assertSame([$first, $second], $this->runtime()->getOrderedChildren($parent));
    }

    private function child(Taxon $parent, string $code): Taxon
    {
        $child = new Taxon();
        $child->setCode($code);
        $parent->addChild($child);

        return $child;
    }

    private function runtime(): MenuRuntime
    {
        return new MenuRuntime($this->createStub(EntityManagerInterface::class), Taxon::class);
    }
}
