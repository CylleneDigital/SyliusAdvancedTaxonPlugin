<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\FacetCondition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class FacetReferenceChecker implements FacetReferenceCheckerInterface, ResetInterface
{
    /** @var array<string, bool> */
    private array $existing = [];

    /**
     * @param class-string $attributeClass
     * @param class-string $optionClass
     * @param class-string $taxonClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $attributeClass,
        private readonly string $optionClass,
        private readonly string $taxonClass,
    ) {
    }

    public function exists(string $conditionType, string $referenceCode): bool
    {
        $class = match ($conditionType) {
            FacetCondition::TYPE_ATTRIBUTE => $this->attributeClass,
            FacetCondition::TYPE_OPTION => $this->optionClass,
            FacetCondition::TYPE_TAXON => $this->taxonClass,
            default => null,
        };

        if ($class === null || $referenceCode === '') {
            return false;
        }

        $key = $conditionType . '/' . $referenceCode;
        if (!isset($this->existing[$key])) {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select('COUNT(resource.id)')
                ->from($class, 'resource')
                ->andWhere('resource.code = :code')
                ->setParameter('code', $referenceCode);

            // Conditions only read the text and select (JSON) values of an attribute: on any other
            // one, a negative condition would match every product.
            if ($conditionType === FacetCondition::TYPE_ATTRIBUTE) {
                $queryBuilder->andWhere('resource.storageType IN (:storage_types)')->setParameter('storage_types', ['text', 'json']);
            }

            $this->existing[$key] = (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
        }

        return $this->existing[$key];
    }

    public function reset(): void
    {
        $this->existing = [];
    }
}
