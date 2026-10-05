<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NotUniverseMainTaxonValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotUniverseMainTaxon) {
            throw new UnexpectedTypeException($constraint, NotUniverseMainTaxon::class);
        }

        if (!$value instanceof ProductInterface) {
            throw new UnexpectedValueException($value, ProductInterface::class);
        }

        $mainTaxon = $value->getMainTaxon();

        if ($mainTaxon instanceof AdvancedTaxonInterface && $mainTaxon->isUniverse()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('mainTaxon')
                ->setParameter('%taxon%', (string) $mainTaxon->getName())
                ->addViolation();
        }
    }
}
