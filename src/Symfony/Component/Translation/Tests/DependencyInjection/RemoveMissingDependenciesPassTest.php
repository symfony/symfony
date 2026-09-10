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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Translation\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheProviderFactoriesAreKeptWhenAnHttpClientIsRegistered()
    {
        $container = $this->createContainer();
        $container->register('http_client');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->has('translation.provider_factory.lokalise'));
        $this->assertTrue($container->has('translation.provider_factory.loco.http_client'));
    }

    public function testTheProviderFactoriesAreDroppedWithoutAnHttpClient()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('translation.provider_factory.lokalise'));
        $this->assertFalse($container->has('translation.provider_factory.loco.http_client'));
        $this->assertTrue($container->has('translation.provider_factory.null'), 'the factories that need no HTTP client stay');
    }

    public function testTheDataCollectorGoesWithTheProfiler()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('data_collector.translation'));
        $this->assertFalse($container->has('translator.data_collector'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ([
            'data_collector.translation',
            'translator.data_collector',
            'translation.provider_factory.null',
            'translation.provider_factory.crowdin',
            'translation.provider_factory.crowdin.http_client',
            'translation.provider_factory.loco',
            'translation.provider_factory.loco.http_client',
            'translation.provider_factory.lokalise',
            'translation.provider_factory.phrase',
            'translation.provider_factory.poeditor',
        ] as $id) {
            $container->register($id);
        }

        return $container;
    }
}
