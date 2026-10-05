<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidFacetConditionValidator extends ConstraintValidator
{
    public function __construct(
        private readonly FacetReferenceCheckerInterface $referenceChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidFacetCondition) {
            throw new UnexpectedTypeException($constraint, ValidFacetCondition::class);
        }

        if (!$value instanceof FacetCondition) {
            throw new UnexpectedValueException($value, FacetCondition::class);
        }

        $type = $value->getConditionType();
        $operators = FacetCondition::OPERATORS_BY_TYPE[$type] ?? null;

        if ($operators === null) {
            $this->context->buildViolation($constraint->invalidTypeMessage)->atPath('conditionType')->addViolation();

            return;
        }

        if (!in_array($value->getOperator(), $operators, true)) {
            $this->context->buildViolation($constraint->invalidOperatorMessage)->atPath('operator')->addViolation();
        }

        $referenceCode = trim((string) $value->getReferenceCode());
        if (FacetCondition::requiresReference($type) && $referenceCode === '') {
            $this->context->buildViolation($constraint->referenceRequiredMessage)->atPath('referenceCode')->addViolation();
        } elseif ($type === FacetCondition::TYPE_TAXON && $referenceCode === $value->getTaxon()?->getCode()) {
            // The products of the taxon would change its own membership on every synchronization.
            $this->context->buildViolation($constraint->selfReferenceMessage)->atPath('referenceCode')->addViolation();
        } elseif (FacetCondition::requiresReference($type) && !$this->referenceChecker->exists($type, $referenceCode)) {
            $this->context->buildViolation($constraint->referenceNotFoundMessage)
                ->atPath('referenceCode')
                ->setParameter('%code%', $referenceCode)
                ->addViolation();
        }

        if (FacetCondition::requiresValue($type) && trim((string) $value->getValue()) === '') {
            $this->context->buildViolation($constraint->valueRequiredMessage)->atPath('value')->addViolation();
        }

        foreach (['referenceCode' => $value->getReferenceCode(), 'value' => $value->getValue()] as $field => $content) {
            if (mb_strlen((string) $content) > FacetCondition::MAX_LENGTH) {
                $this->context->buildViolation($constraint->tooLongMessage)
                    ->atPath($field)
                    ->setParameter('%limit%', (string) FacetCondition::MAX_LENGTH)
                    ->addViolation();
            }
        }
    }
}
