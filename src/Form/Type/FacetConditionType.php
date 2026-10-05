<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FacetConditionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allOperators = [];
        foreach (FacetCondition::OPERATORS_BY_TYPE as $ops) {
            foreach ($ops as $op) {
                $allOperators['cyllene_digital_sylius_advanced_taxon.facet.operator.' . $op] = $op;
            }
        }

        $builder
            ->add('conditionType', ChoiceType::class, [
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.facet.condition_type',
                'choices' => [
                    'cyllene_digital_sylius_advanced_taxon.facet.type.attribute' => FacetCondition::TYPE_ATTRIBUTE,
                    'cyllene_digital_sylius_advanced_taxon.facet.type.option' => FacetCondition::TYPE_OPTION,
                    'cyllene_digital_sylius_advanced_taxon.facet.type.name' => FacetCondition::TYPE_NAME,
                    'cyllene_digital_sylius_advanced_taxon.facet.type.description' => FacetCondition::TYPE_DESCRIPTION,
                    'cyllene_digital_sylius_advanced_taxon.facet.type.stock' => FacetCondition::TYPE_STOCK,
                    'cyllene_digital_sylius_advanced_taxon.facet.type.taxon_membership' => FacetCondition::TYPE_TAXON,
                ],
                'attr' => [
                    'class' => 'form-select form-select-sm',
                    'data-facet-type' => 'true',
                ],
            ])
            ->add('operator', ChoiceType::class, [
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.facet.operator',
                'choices' => $allOperators,
                'attr' => [
                    'class' => 'form-select form-select-sm',
                    'data-facet-operator' => 'true',
                ],
            ])
            ->add('referenceCode', HiddenType::class, [
                'label' => false,
                'required' => false,
                // The reference is picked through a select rendered by JS: its error is displayed
                // with the condition row instead of bubbling up to the whole taxon form.
                'error_bubbling' => false,
                'attr' => ['data-facet-reference-code' => 'true'],
            ])
            ->add('value', TextType::class, [
                'label' => 'cyllene_digital_sylius_advanced_taxon.form.facet.value',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'data-facet-value' => 'true',
                    'placeholder' => 'cyllene_digital_sylius_advanced_taxon.form.facet.value_placeholder',
                ],
            ])
            ->add('position', HiddenType::class, [
                'label' => false,
                'empty_data' => 0,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FacetCondition::class,
            'label' => false,
        ]);
    }
}
