<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete\NonUniverseTaxonAutocompleteType;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType as AdminProductType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

#[AutoconfigureTag('form.type_extension', ['extended-type' => AdminProductType::class])]
final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('mainTaxon', NonUniverseTaxonAutocompleteType::class, [
            'label' => 'sylius.form.product.main_taxon',
            'multiple' => false,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminProductType::class];
    }
}
