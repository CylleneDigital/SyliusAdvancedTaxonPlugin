<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Validator;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints\NotUniverseMainTaxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Validator\Constraints\NotUniverseMainTaxonValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon as SyliusTaxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;

/**
 * @extends ConstraintValidatorTestCase<NotUniverseMainTaxonValidator>
 */
final class NotUniverseMainTaxonValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotUniverseMainTaxonValidator();
    }

    /**
     * @return iterable<string, array{TaxonInterface|null}>
     */
    public static function acceptedMainTaxons(): iterable
    {
        yield 'no main taxon' => [null];
        yield 'a regular taxon' => [self::createTaxon(false)];
        yield 'a taxon of another model' => [new SyliusTaxon()];
    }

    #[DataProvider('acceptedMainTaxons')]
    public function test_it_accepts_a_main_taxon_that_is_not_a_universe(?TaxonInterface $mainTaxon): void
    {
        $product = new Product();
        $product->setMainTaxon($mainTaxon);

        $this->validator->validate($product, new NotUniverseMainTaxon());

        $this->assertNoViolation();
    }

    public function test_it_only_validates_products(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(self::createTaxon(true), new NotUniverseMainTaxon());
    }

    public function test_it_only_handles_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Product(), new NotBlank());
    }

    public function test_it_rejects_a_universe_as_main_taxon(): void
    {
        $product = new Product();
        $product->setMainTaxon(self::createTaxon(true));

        $constraint = new NotUniverseMainTaxon();
        $this->validator->validate($product, $constraint);

        $this->buildViolation($constraint->message)
            ->atPath('property.path.mainTaxon')
            ->setParameter('%taxon%', 'Clothing')
            ->assertRaised();
    }

    private static function createTaxon(bool $isUniverse): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName('Clothing');
        $taxon->setIsUniverse($isUniverse);

        return $taxon;
    }
}
