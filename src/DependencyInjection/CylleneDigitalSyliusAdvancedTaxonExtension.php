<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CylleneDigitalSyliusAdvancedTaxonExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /**
     * Migrations shipped by the plugin live in this namespace.
     */
    private const MIGRATIONS_NAMESPACE = 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations';

    /**
     * Bundle-relative path of the plugin migrations directory.
     */
    private const MIGRATIONS_DIRECTORY = '@CylleneDigitalSyliusAdvancedTaxonPlugin/src/Migrations';

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->getProcessedConfiguration($configs);

        $container->setParameter(
            'cyllene_digital_sylius_advanced_taxon.icon_libraries',
            $this->buildIconLibraries($config['icon_libraries']),
        );

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.yaml');
    }

    /**
     * @param array<string, array{label: string|null, icon_prefix: string, icons: array<int, string>}> $configuredLibraries
     *
     * @return array<int, array{key: string, label: string, icon_prefix: string, icons: array<int, string>}>
     */
    private function buildIconLibraries(array $configuredLibraries): array
    {
        $libraries = [
            'tabler' => [
                'label' => 'Tabler',
                'icon_prefix' => 'tabler',
                'icons' => $this->discoverTablerIcons(),
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
                'label' => $library['label'] ?: ucfirst(str_replace(['_', '-'], ' ', $key)),
                'icon_prefix' => $library['icon_prefix'],
                'icons' => $library['icons'],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function discoverTablerIcons(): array
    {
        $iconsDirectory = dirname(__DIR__, 2) . '/vendor/sylius/sylius/src/Sylius/Bundle/UiBundle/Resources/assets/icons/tabler';
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

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                self::MIGRATIONS_NAMESPACE => self::MIGRATIONS_DIRECTORY,
            ],
        ]);

        $this->prependDoctrineMigrations($container);
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
