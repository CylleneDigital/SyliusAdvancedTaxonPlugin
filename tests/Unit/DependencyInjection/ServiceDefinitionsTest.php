<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Contracts\Service\ResetInterface;

final class ServiceDefinitionsTest extends TestCase
{
    /**
     * A service keeping memory between requests is only reset (FrankenPHP worker, Messenger
     * consumer) when tagged: without autoconfiguration, the tag is set by hand.
     */
    public function test_every_resettable_service_is_tagged_for_the_kernel_reset(): void
    {
        $container = new ContainerBuilder();
        $directory = dirname(__DIR__, 3) . '/config/services';
        $loader = new PhpFileLoader($container, new FileLocator($directory));
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $loader->load(basename($file));
        }

        $checked = 0;
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null || !is_a($class, ResetInterface::class, true)) {
                continue;
            }

            ++$checked;
            self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'), sprintf('"%s" is not reset between requests.', $id));
        }

        self::assertGreaterThanOrEqual(6, $checked);
    }
}
