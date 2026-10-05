<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * The icon libraries offered by the icon picker of the taxon form.
 */
final class IconLibrariesRuntime implements RuntimeExtensionInterface
{
    /**
     * @param list<array{key: string, label: string, icon_prefix: string, icons: array<int, string>}> $iconLibraries
     */
    public function __construct(
        private readonly array $iconLibraries,
    ) {
    }

    /**
     * @return list<array{key: string, label: string, icon_prefix: string, icons: array<int, string>}>
     */
    public function getIconLibraries(): array
    {
        return $this->iconLibraries;
    }
}
