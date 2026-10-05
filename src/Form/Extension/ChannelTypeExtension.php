<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\ChannelType as AdminChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

final class ChannelTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('hasMegaMenu', CheckboxType::class, [
            'required' => false,
            'label' => 'cyllene_digital_sylius_advanced_taxon.form.has_mega_menu',
            'help' => 'cyllene_digital_sylius_advanced_taxon.form.has_mega_menu_description',
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminChannelType::class];
    }
}
