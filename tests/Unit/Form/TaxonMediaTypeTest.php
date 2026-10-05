<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Form;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\TaxonMediaType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;

final class TaxonMediaTypeTest extends TestCase
{
    /**
     * Only zones rendering their media as product-like cards expose the card text toggle.
     */
    public function test_it_exposes_the_card_text_toggle_when_the_zone_requires_it(): void
    {
        $builder = $this->buildMediaBuilder(['show_card_text' => true]);

        self::assertTrue($builder->has('showCardText'));
    }

    public function test_it_does_not_expose_the_card_text_toggle_otherwise(): void
    {
        $builder = $this->buildMediaBuilder();

        self::assertFalse($builder->has('showCardText'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildMediaBuilder(array $options = []): FormBuilderInterface
    {
        $factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $builder = $factory->createBuilder(FormType::class, null);

        $type = new TaxonMediaType(TaxonImage::class);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        /** @var array<string, mixed> $resolvedOptions */
        $resolvedOptions = $resolver->resolve($options);

        $type->buildForm($builder, $resolvedOptions);

        return $builder;
    }
}
