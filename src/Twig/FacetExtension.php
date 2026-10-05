<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\FacetProductQueryBuilder;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Repository\ProductAttributeRepositoryInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AutoconfigureTag('twig.extension')]
final class FacetExtension extends AbstractExtension
{
    /**
     * Upper bound of products rendered by the storefront helpers, to keep templates bounded.
     */
    private const MAX_PRODUCTS = 200;

    /**
     * @param ProductAttributeRepositoryInterface<ProductAttributeInterface> $attributeRepository
     * @param ProductOptionRepositoryInterface<ProductOptionInterface> $optionRepository
     * @param TaxonRepositoryInterface<Taxon> $taxonRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product_attribute')]
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        #[Autowire(service: 'sylius.repository.product_option')]
        private readonly ProductOptionRepositoryInterface $optionRepository,
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly FacetProductQueryBuilder $facetProductQueryBuilder,
        private readonly RequestStack $requestStack,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('advanced_taxon_facet_choices', [$this, 'getFacetChoices']),
            new TwigFunction('advanced_taxon_taxon_products', [$this, 'getTaxonProducts']),
            new TwigFunction('advanced_taxon_filtered_products', [$this, 'getFilteredTaxonProducts']),
            new TwigFunction('advanced_taxon_available_filters', [$this, 'getAvailableFilters']),
            new TwigFunction('advanced_taxon_taxon_from_request', [$this, 'getTaxonFromRequest']),
            new TwigFunction('advanced_taxon_reference_label', [$this, 'getReferenceLabel']),
        ];
    }

    /**
     * Resolves the current taxon from the request slug (like BreadcrumbComponent).
     */
    public function getTaxonFromRequest(): ?Taxon
    {
        $slug = $this->requestStack->getCurrentRequest()?->attributes->get('slug');

        if (!is_string($slug) || $slug === '') {
            return null;
        }

        $taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());

        return $taxon instanceof Taxon ? $taxon : null;
    }

    /**
     * Returns the human-readable label for a facet condition reference code.
     * Falls back to the code itself if no label is found.
     */
    public function getReferenceLabel(string $conditionType, string $referenceCode): string
    {
        if ($referenceCode === '') {
            return '';
        }

        $entity = match ($conditionType) {
            'attribute' => $this->attributeRepository->findOneBy(['code' => $referenceCode]),
            'option' => $this->optionRepository->findOneBy(['code' => $referenceCode]),
            'taxon_membership' => $this->taxonRepository->findOneBy(['code' => $referenceCode]),
            default => null,
        };

        $name = $entity?->getName();

        return (is_string($name) && $name !== '') ? $name : $referenceCode;
    }

    /**
     * Returns products linked to the current taxon, and optionally to all its descendants.
     *
     * @return array<int, object>
     */
    public function getTaxonProducts(Taxon $taxon, string $localeCode, bool $includeChildren = false): array
    {
        /** @var array<int, object> $products */
        $products = $this->facetProductQueryBuilder
            ->buildTaxonProductsQuery($taxon, $localeCode, $includeChildren)
            ->setMaxResults(self::MAX_PRODUCTS)
            ->getQuery()
            ->getResult();

        return $products;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, object>
     */
    public function getFilteredTaxonProducts(
        Taxon $taxon,
        string $localeCode,
        bool $includeChildren = false,
        array $filters = [],
    ): array {
        /** @var array<int, object> $products */
        $products = $this->facetProductQueryBuilder
            ->buildAdvancedFilteredTaxonProductsQuery($taxon, $localeCode, $includeChildren, $filters)
            ->setMaxResults(self::MAX_PRODUCTS)
            ->getQuery()
            ->getResult();

        return $products;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function getAvailableFilters(
        Taxon $taxon,
        string $localeCode,
        bool $includeChildren = false,
        array $filters = [],
    ): array {
        return $this->facetProductQueryBuilder
            ->getAdvancedFiltersData($taxon, $localeCode, $includeChildren, $filters);
    }

    /**
     * Returns an array of choices organized by condition type, for use in the facet condition JS.
     *
     * @return array{attribute: array<string,string>, option: array<string,string>, taxon_membership: array<string,string>}
     */
    public function getFacetChoices(): array
    {
        $attributeChoices = [];
        foreach ($this->attributeRepository->findAll() as $attribute) {
            $attributeChoices[(string) ($attribute->getName() ?: $attribute->getCode())] = (string) $attribute->getCode();
        }
        asort($attributeChoices);

        $optionChoices = [];
        foreach ($this->optionRepository->findAll() as $option) {
            $optionChoices[(string) ($option->getName() ?: $option->getCode())] = (string) $option->getCode();
        }
        asort($optionChoices);

        $taxonChoices = [];
        foreach ($this->taxonRepository->findAll() as $taxon) {
            $code = $taxon->getCode();
            if ($code === null) {
                continue;
            }

            $taxonChoices[(string) ($taxon->getName() ?: $code)] = (string) $code;
        }
        asort($taxonChoices);

        return [
            'attribute' => $attributeChoices,
            'option' => $optionChoices,
            'taxon_membership' => $taxonChoices,
        ];
    }
}
