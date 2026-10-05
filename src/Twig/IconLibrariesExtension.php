<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AutoconfigureTag('twig.extension')]
final class IconLibrariesExtension extends AbstractExtension
{
    private const ICON_LIBRARIES_PARAMETER = 'cyllene_digital_sylius_advanced_taxon.icon_libraries';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('advanced_taxon_icon_libraries', [$this, 'getIconLibraries']),
            new TwigFunction('advanced_taxon_ordered_children', [$this, 'getOrderedChildren']),
        ];
    }

    /**
     * Returns the enabled children of a taxon, with the featured ones moved to the front
     * while keeping the configured order and removing duplicates.
     *
     * @return array<int, TaxonInterface>
     */
    public function getOrderedChildren(TaxonInterface $taxon): array
    {
        $enabledChildren = [];
        foreach ($taxon->getEnabledChildren() as $enabledChild) {
            $enabledChildren[$this->getTaxonKey($enabledChild)] = $enabledChild;
        }

        $orderedChildren = [];
        $seenKeys = [];

        $featuredChildren = $taxon instanceof Taxon ? $taxon->getFeaturedChildren() : [];
        foreach ($featuredChildren as $featuredChild) {
            $key = $this->getTaxonKey($featuredChild);

            if (!isset($enabledChildren[$key]) || isset($seenKeys[$key])) {
                continue;
            }

            $orderedChildren[] = $featuredChild;
            $seenKeys[$key] = true;
        }

        foreach ($enabledChildren as $key => $enabledChild) {
            if (isset($seenKeys[$key])) {
                continue;
            }

            $orderedChildren[] = $enabledChild;
            $seenKeys[$key] = true;
        }

        return $orderedChildren;
    }

    /**
     * @return list<array{key: string, label: string, icon_prefix: string, icons: array<int, string>}>
     */
    public function getIconLibraries(): array
    {
        $value = $this->parameterBag->get(self::ICON_LIBRARIES_PARAMETER);

        if (!is_array($value)) {
            return [];
        }

        /** @var list<array{key: string, label: string, icon_prefix: string, icons: array<int, string>}> $libraries */
        $libraries = array_values($value);

        return $libraries;
    }

    private function getTaxonKey(TaxonInterface $taxon): string
    {
        $id = $taxon->getId();

        return is_scalar($id) ? (string) $id : spl_object_hash($taxon);
    }
}
