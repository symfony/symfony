<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Translation\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheProviderFactoriesAreKeptWhenAnHttpClientIsRegistered()
    {
        $container = $this->createContainer();
        $container->register('http_client');

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->has('translation.provider_factory.lokalise'));
        $this->assertTrue($container->has('translation.provider_factory.loco.http_client'));
    }

    public function testTheProviderFactoriesAreDroppedWithoutAnHttpClient()
    {
        $container = $this->createContainer();

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('translation.provider_factory.lokalise'));
        $this->assertFalse($container->has('translation.provider_factory.loco.http_client'));
        $this->assertTrue($container->has('translation.provider_factory.null'), 'the factories that need no HTTP client stay');
    }

    public function testTheDataCollectorGoesWithTheProfiler()
    {
        $container = $this->createContainer();

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('data_collector.translation'));
        $this->assertFalse($container->has('translator.data_collector'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => '/app',
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.cache_dir' => sys_get_temp_dir(),
        ]));
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/Resources/config'));
        $loader->load('translation_debug.php');
        $loader->load('translation_providers.php');

        return $container;
    }
}
