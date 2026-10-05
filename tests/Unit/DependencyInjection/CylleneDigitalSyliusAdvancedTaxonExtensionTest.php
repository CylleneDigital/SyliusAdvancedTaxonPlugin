<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\DependencyInjection;

use CylleneDigital\SyliusAdvancedTaxonPlugin\DependencyInjection\CylleneDigitalSyliusAdvancedTaxonExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\SyliusUiBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class CylleneDigitalSyliusAdvancedTaxonExtensionTest extends TestCase
{
    public function test_it_discovers_the_tabler_icons_shipped_by_the_sylius_ui_bundle(): void
    {
        $libraries = $this->loadIconLibraries([]);

        self::assertSame('tabler', $libraries[0]['key']);
        self::assertSame('tabler', $libraries[0]['icon_prefix']);
        self::assertNotEmpty($libraries[0]['icons']);
        self::assertContains('archive', $libraries[0]['icons']);
    }

    public function test_it_appends_the_configured_icon_libraries(): void
    {
        $libraries = $this->loadIconLibraries([
            'icon_libraries' => [
                'phosphor' => ['icon_prefix' => 'ph', 'icons' => ['tree', '', 'heart', 'tree']],
            ],
        ]);

        self::assertCount(2, $libraries);
        self::assertSame(
            ['key' => 'phosphor', 'label' => 'Phosphor', 'icon_prefix' => 'ph', 'icons' => ['heart', 'tree']],
            $libraries[1],
        );
    }

    public function test_a_library_without_label_is_named_after_its_key(): void
    {
        $libraries = $this->loadIconLibraries(['icon_libraries' => ['font_awesome-solid' => ['icon_prefix' => 'fa6-solid']]]);

        self::assertSame('Font awesome solid', $libraries[1]['label']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidLibraries(): iterable
    {
        yield 'no icon prefix' => [['icons' => ['tree']]];
        yield 'empty icon prefix' => [['icon_prefix' => '']];
    }

    /**
     * @param array<string, mixed> $library
     */
    #[DataProvider('invalidLibraries')]
    public function test_a_library_needs_an_icon_prefix(array $library): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadIconLibraries(['icon_libraries' => ['phosphor' => $library]]);
    }

    public function test_the_image_filters_are_only_declared_when_liip_imagine_is_installed(): void
    {
        $container = new ContainerBuilder();
        (new CylleneDigitalSyliusAdvancedTaxonExtension())->prepend($container);
        self::assertSame([], $container->getExtensionConfig('liip_imagine'));

        $container = new ContainerBuilder();
        $container->registerExtension($this->emptyExtension('liip_imagine'));
        (new CylleneDigitalSyliusAdvancedTaxonExtension())->prepend($container);

        /** @var array{filter_sets: array<string, mixed>} $liipImagine */
        $liipImagine = $container->getExtensionConfig('liip_imagine')[0];
        self::assertArrayHasKey('cyllene_advanced_taxon_icon', $liipImagine['filter_sets']);
    }

    /**
     * Every icon a template names statically is served from local files: a plugin icon from its
     * own set, any other one from the Tabler set shipped by Sylius. None depends on Iconify.
     */
    public function test_every_template_icon_is_shipped_locally(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->emptyExtension('ux_icons'));
        (new CylleneDigitalSyliusAdvancedTaxonExtension())->prepend($container);

        /** @var array{icon_sets: array<string, array{path: string}>} $uxIcons */
        $uxIcons = $container->getExtensionConfig('ux_icons')[0];
        $setDirectories = [
            'at' => $uxIcons['icon_sets']['at']['path'],
            'tabler' => dirname((string) (new \ReflectionClass(SyliusUiBundle::class))->getFileName()) . '/Resources/assets/icons/tabler',
        ];

        $templates = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/templates', \FilesystemIterator::SKIP_DOTS));
        $icons = [];
        foreach ($templates as $template) {
            self::assertInstanceOf(\SplFileInfo::class, $template);
            preg_match_all('/ux_icon\([\'"]([a-z0-9-]+):([a-z0-9-]+)[\'"]/', (string) file_get_contents($template->getPathname()), $matches, \PREG_SET_ORDER);
            foreach ($matches as [, $prefix, $name]) {
                $icons[$prefix . ':' . $name] = [$prefix, $name];
            }
        }

        self::assertNotEmpty($icons);
        foreach ($icons as $icon => [$prefix, $name]) {
            self::assertArrayHasKey($prefix, $setDirectories, sprintf('"%s" would be fetched from Iconify at render time.', $icon));
            self::assertFileExists(sprintf('%s/%s.svg', $setDirectories[$prefix], $name), sprintf('"%s" is not shipped.', $icon));
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{key: string, label: string, icon_prefix: string, icons: list<string>}>
     */
    private function loadIconLibraries(array $config): array
    {
        $container = new ContainerBuilder();
        (new CylleneDigitalSyliusAdvancedTaxonExtension())->load([$config], $container);

        /** @var list<array{key: string, label: string, icon_prefix: string, icons: list<string>}> $libraries */
        $libraries = $container->getParameter('cyllene_digital_sylius_advanced_taxon.icon_libraries');

        return $libraries;
    }

    private function emptyExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
