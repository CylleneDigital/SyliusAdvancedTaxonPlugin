<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class FacetRuntime implements RuntimeExtensionInterface, ResetInterface
{
    /**
     * Taxon of the current page by slug and locale: every template of the page asks for it.
     *
     * @var array<string, ?AdvancedTaxonInterface>
     */
    private array $taxonsBySlug = [];

    /**
     * @param TaxonRepositoryInterface<AdvancedTaxonInterface> $taxonRepository
     * @param class-string $attributeClass
     * @param class-string $optionClass
     * @param class-string $taxonClass
     */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly FacetProductQueryBuilder $facetProductQueryBuilder,
        private readonly RequestStack $requestStack,
        private readonly LocaleContextInterface $localeContext,
        private readonly bool $gridIncludesAllDescendants,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $attributeClass,
        private readonly string $optionClass,
        private readonly string $taxonClass,
    ) {
    }

    /**
     * Resolves the current taxon from the request slug (like BreadcrumbComponent).
     */
    public function getTaxonFromRequest(): ?AdvancedTaxonInterface
    {
        $slug = $this->requestStack->getCurrentRequest()?->attributes->get('slug');

        if (!is_string($slug) || $slug === '') {
            return null;
        }

        $localeCode = $this->localeContext->getLocaleCode();
        $key = $localeCode . '/' . $slug;

        if (!array_key_exists($key, $this->taxonsBySlug)) {
            $taxon = $this->taxonRepository->findOneBySlug($slug, $localeCode);
            $this->taxonsBySlug[$key] = $taxon instanceof AdvancedTaxonInterface ? $taxon : null;
        }

        return $this->taxonsBySlug[$key];
    }

    public function reset(): void
    {
        $this->taxonsBySlug = [];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function getAvailableFilters(
        AdvancedTaxonInterface $taxon,
        string $localeCode,
        bool $includeChildren = false,
        array $filters = [],
    ): array {
        // The facets count exactly what the shop product grid lists: same descendants, same search.
        $criteria = $this->requestStack->getCurrentRequest()?->query->all('criteria') ?? [];
        $search = is_array($criteria['search'] ?? null) ? ($criteria['search']['value'] ?? '') : '';

        return $this->facetProductQueryBuilder->getAdvancedFiltersData(
            $taxon,
            $localeCode,
            $includeChildren || $this->gridIncludesAllDescendants,
            ['search' => is_string($search) ? $search : ''] + $filters,
        );
    }

    /**
     * Returns the references a facet condition can target (code => label), by condition type, for
     * the facet condition JS. Labels are read in the current locale with scalar queries: the admin
     * form never hydrates the whole catalog structure.
     *
     * @return array{attribute: array<string, string>, option: array<string, string>, taxon_membership: array<string, string>}
     */
    public function getFacetChoices(): array
    {
        return [
            // Conditions only read the text and select (JSON) values of an attribute.
            'attribute' => $this->findChoices($this->attributeClass, 'name', ['text', 'json']),
            'option' => $this->findChoices($this->optionClass, 'name'),
            'taxon_membership' => $this->findChoices($this->taxonClass, 'name'),
        ];
    }

    /**
     * @param class-string $class
     * @param list<string>|null $storageTypes attribute storage types to keep
     *
     * @return array<string, string> label => code, sorted by label; a label shared by several
     *                               references carries its code, so that every one stays selectable
     */
    private function findChoices(string $class, string $labelField, ?array $storageTypes = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('resource.code AS code', sprintf('translation.%s AS label', $labelField))
            ->from($class, 'resource')
            ->leftJoin('resource.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('resource.code IS NOT NULL')
            ->setParameter('locale', $this->localeContext->getLocaleCode());

        if ($storageTypes !== null) {
            $queryBuilder->andWhere('resource.storageType IN (:storage_types)')->setParameter('storage_types', $storageTypes);
        }

        /** @var list<array{code: string|null, label: string|null}> $rows */
        $rows = $queryBuilder->getQuery()->getArrayResult();

        $labels = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $label = $row['label'] ?? '';
            $labels[$code] = $label !== '' ? $label : $code;
        }

        $occurrences = array_count_values($labels);
        $choices = [];
        foreach ($labels as $code => $label) {
            $choices[$occurrences[$label] > 1 ? sprintf('%s (%s)', $label, $code) : $label] = (string) $code;
        }
        ksort($choices, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $choices;
    }
}
