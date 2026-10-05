<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\UiBundle\SyliusUiBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class CylleneDigitalSyliusAdvancedTaxonExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    private const string MIGRATIONS_NAMESPACE = 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations';

    private const string MIGRATIONS_DIRECTORY = '@CylleneDigitalSyliusAdvancedTaxonPlugin/src/Migrations';

    /**
     * Prefix of the icons shipped by the plugin (`at:layout-grid`).
     */
    private const string ICON_SET = 'at';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getProcessedConfiguration($configs);

        $container->setParameter(
            'cyllene_digital_sylius_advanced_taxon.icon_libraries',
            $this->buildIconLibraries($container, $config['icon_libraries']),
        );

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    /**
     * @param array<string, array{label: string|null, icon_prefix: string, icons: array<int, string>}> $configuredLibraries
     *
     * @return array<int, array{key: string, label: string, icon_prefix: string, icons: array<int, string>}>
     */
    private function buildIconLibraries(ContainerBuilder $container, array $configuredLibraries): array
    {
        $libraries = [
            'tabler' => [
                'label' => 'Tabler',
                'icon_prefix' => 'tabler',
                'icons' => $this->discoverTablerIcons($container),
            ],
        ];

        foreach ($configuredLibraries as $key => $library) {
            $icons = array_values(array_unique(array_filter($library['icons'], static fn (string $icon): bool => $icon !== '')));
            sort($icons);

            $libraries[$key] = [
                'label' => $library['label'] ?: ucfirst(str_replace(['_', '-'], ' ', $key)),
                'icon_prefix' => $library['icon_prefix'],
                'icons' => $icons,
            ];
        }

        $normalized = [];

        foreach ($libraries as $key => $library) {
            $normalized[] = [
                'key' => $key,
                'label' => $library['label'],
                'icon_prefix' => $library['icon_prefix'],
                'icons' => $library['icons'],
            ];
        }

        return $normalized;
    }

    /**
     * Lists the Tabler icons shipped by the Sylius UI bundle, wherever Composer installed it.
     *
     * @return array<int, string>
     */
    private function discoverTablerIcons(ContainerBuilder $container): array
    {
        $bundleFile = (new \ReflectionClass(SyliusUiBundle::class))->getFileName();
        if ($bundleFile === false) {
            return [];
        }

        $iconsDirectory = dirname($bundleFile) . '/Resources/assets/icons/tabler';
        if (!is_dir($iconsDirectory)) {
            return [];
        }

        $container->addResource(new DirectoryResource($iconsDirectory, '/\.svg$/'));
        $paths = glob($iconsDirectory . '/*.svg') ?: [];

        $icons = [];
        foreach ($paths as $path) {
            $icon = pathinfo($path, \PATHINFO_FILENAME);
            if ($icon !== '') {
                $icons[] = $icon;
            }
        }

        $icons = array_values(array_unique($icons));
        sort($icons);

        return $icons;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'CylleneDigitalSyliusAdvancedTaxonPlugin' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/../Entity',
                        'prefix' => 'CylleneDigital\\SyliusAdvancedTaxonPlugin\\Entity',
                        'alias' => 'CylleneDigitalSyliusAdvancedTaxonPlugin',
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);

        // Icons of the plugin templates that Sylius does not ship: served from the plugin, never
        // fetched from Iconify at render time. Its own prefix, so an application set is never shadowed.
        $container->prependExtensionConfig('ux_icons', [
            'icon_sets' => [
                self::ICON_SET => ['path' => dirname(__DIR__, 2) . '/assets/icons'],
            ],
        ]);

        if ($container->hasExtension('liip_imagine')) {
            // Prepended: an application redefining these filter sets keeps its own version.
            $container->prependExtensionConfig('liip_imagine', [
                'filter_sets' => [
                    'cyllene_advanced_taxon_icon' => [
                        'filters' => ['thumbnail' => ['size' => [160, 160], 'mode' => 'inset']],
                    ],
                    'cyllene_universe_slider_mobile' => [
                        'filters' => [
                            'thumbnail' => ['size' => [640, 360], 'mode' => 'outbound'],
                            'strip' => [],
                        ],
                        'quality' => 82,
                    ],
                ],
            ]);
        }
    }

    protected function getMigrationsNamespace(): string
    {
        return self::MIGRATIONS_NAMESPACE;
    }

    protected function getMigrationsDirectory(): string
    {
        return self::MIGRATIONS_DIRECTORY;
    }

    /**
     * @return array<int, string>
     */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    /**
     * @param array<array<mixed>> $configs
     *
     * @return array{
     *     icon_libraries: array<string, array{label: string|null, icon_prefix: string, icons: array<int, string>}>
     * }
     */
    private function getProcessedConfiguration(array $configs): array
    {
        /** @var array{
         *     icon_libraries: array<string, array{label: string|null, icon_prefix: string, icons: array<int, string>}>
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        return $config;
    }
}
