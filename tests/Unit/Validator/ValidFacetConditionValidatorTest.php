<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Validator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceCheckerInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints\ValidFacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints\ValidFacetConditionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

/**
 * @extends ConstraintValidatorTestCase<ValidFacetConditionValidator>
 */
final class ValidFacetConditionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidFacetConditionValidator(new class() implements FacetReferenceCheckerInterface {
            public function exists(string $conditionType, string $referenceCode): bool
            {
                return $referenceCode !== 'deleted';
            }
        });
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function validConditionProvider(): iterable
    {
        yield 'attribute' => [FacetCondition::TYPE_ATTRIBUTE, 'equals', 'material', 'cotton'];
        yield 'option' => [FacetCondition::TYPE_OPTION, 'not_in', 'size', 'XL'];
        yield 'name' => [FacetCondition::TYPE_NAME, 'contains', null, 'watch'];
        yield 'description' => [FacetCondition::TYPE_DESCRIPTION, 'not_contains', null, 'leather'];
        yield 'stock without value' => [FacetCondition::TYPE_STOCK, 'is_in_stock', null, null];
        yield 'taxon without value' => [FacetCondition::TYPE_TAXON, 'in', 'caps', null];
    }

    #[DataProvider('validConditionProvider')]
    public function test_it_accepts_a_complete_condition(string $type, string $operator, ?string $reference, ?string $value): void
    {
        $this->validator->validate($this->createCondition($type, $operator, $reference, $value), new ValidFacetCondition());

        $this->assertNoViolation();
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition('price', 'equals', null, '10'), $constraint);

        $this->buildViolation($constraint->invalidTypeMessage)->atPath('property.path.conditionType')->assertRaised();
    }

    public function test_it_rejects_an_operator_of_another_type(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition(FacetCondition::TYPE_OPTION, 'contains', 'size', 'XL'), $constraint);

        $this->buildViolation($constraint->invalidOperatorMessage)->atPath('property.path.operator')->assertRaised();
    }

    public function test_it_requires_a_reference_and_a_value(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition(FacetCondition::TYPE_ATTRIBUTE, 'contains', ' ', ''), $constraint);

        $this->buildViolation($constraint->referenceRequiredMessage)->atPath('property.path.referenceCode')
            ->buildNextViolation($constraint->valueRequiredMessage)->atPath('property.path.value')
            ->assertRaised();
    }

    public function test_it_rejects_a_value_longer_than_its_column(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition(FacetCondition::TYPE_NAME, 'contains', null, str_repeat('a', 256)), $constraint);

        $this->buildViolation($constraint->tooLongMessage)
            ->atPath('property.path.value')
            ->setParameter('%limit%', '255')
            ->assertRaised();
    }

    public function test_it_rejects_a_reference_longer_than_its_column(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition(FacetCondition::TYPE_ATTRIBUTE, 'equals', str_repeat('a', 256), 'cotton'), $constraint);

        $this->buildViolation($constraint->tooLongMessage)
            ->atPath('property.path.referenceCode')
            ->setParameter('%limit%', '255')
            ->assertRaised();
    }

    public function test_it_only_validates_facet_conditions(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('name contains watch', new ValidFacetCondition());
    }

    public function test_it_rejects_a_reference_that_does_not_exist(): void
    {
        $constraint = new ValidFacetCondition();
        $this->validator->validate($this->createCondition(FacetCondition::TYPE_ATTRIBUTE, 'not_equals', 'deleted', 'cotton'), $constraint);

        $this->buildViolation($constraint->referenceNotFoundMessage)
            ->atPath('property.path.referenceCode')
            ->setParameter('%code%', 'deleted')
            ->assertRaised();
    }

    public function test_a_taxon_cannot_be_the_reference_of_its_own_condition(): void
    {
        $taxon = new Taxon();
        $taxon->setCode('caps');
        $condition = $this->createCondition(FacetCondition::TYPE_TAXON, 'not_in', 'caps', null);
        $taxon->addFacetCondition($condition);

        $constraint = new ValidFacetCondition();
        $this->validator->validate($condition, $constraint);

        $this->buildViolation($constraint->selfReferenceMessage)->atPath('property.path.referenceCode')->assertRaised();
    }

    private function createCondition(string $type, string $operator, ?string $reference, ?string $value): FacetCondition
    {
        $condition = new FacetCondition();
        $condition->setConditionType($type);
        $condition->setOperator($operator);
        $condition->setReferenceCode($reference);
        $condition->setValue($value);

        return $condition;
    }
}
