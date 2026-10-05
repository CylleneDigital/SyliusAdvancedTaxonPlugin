<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetReferenceChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Service\ResetInterface;
use Webmozart\Assert\Assert;

/**
 * The plugin services that cache database reads are reset between two requests of a long running
 * process (FrankenPHP worker, Messenger consumer): a stale cache would answer wrongly in silence.
 */
final class ResettableServicesTest extends KernelTestCase
{
    public function test_the_reference_checker_forgets_what_it_cached_when_the_kernel_resets(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $entityManager->beginTransaction();

        try {
            $checker = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.facet.reference_checker');
            Assert::isInstanceOf($checker, FacetReferenceChecker::class);
            $catalog = new CatalogBuilder(self::getContainer(), $entityManager);
            $code = $catalog->code('Material');

            self::assertFalse($checker->exists(FacetCondition::TYPE_ATTRIBUTE, $code));

            $catalog->attribute('Material', 'text', 'Material');
            self::assertFalse($checker->exists(FacetCondition::TYPE_ATTRIBUTE, $code), 'Cached within a request.');

            $resetter = self::getContainer()->get('services_resetter');
            // The services_resetter class moved from HttpKernel to DependencyInjection in Symfony 8.
            Assert::isInstanceOf($resetter, ResetInterface::class);
            $resetter->reset();

            self::assertTrue($checker->exists(FacetCondition::TYPE_ATTRIBUTE, $code));
        } finally {
            $entityManager->rollback();
        }
    }
}
