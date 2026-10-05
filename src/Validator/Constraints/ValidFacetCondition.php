<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidFacetCondition extends Constraint
{
    public string $invalidTypeMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.invalid_type';

    public string $invalidOperatorMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.invalid_operator';

    public string $referenceRequiredMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.reference_required';

    public string $valueRequiredMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.value_required';

    public string $referenceNotFoundMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.reference_not_found';

    public string $selfReferenceMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.self_reference';

    public string $tooLongMessage = 'cyllene_digital_sylius_advanced_taxon.facet_condition.too_long';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
