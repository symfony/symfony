<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheCachePoolIsKeptWhenTheAppPoolExists()
    {
        $container = $this->createContainer();
        $container->register('cache.app');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('cache.http_client'));
        $this->assertTrue($container->hasDefinition('cache.http_client.pool'));
    }

    public function testTheCachePoolIsDroppedWhenTheAppPoolIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('cache.http_client'));
        $this->assertFalse($container->hasDefinition('cache.http_client.pool'));
    }

    public function testTheDataCollectorIsKeptWhenTheProfilerExists()
    {
        $container = $this->createContainer();
        $container->register('profiler');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('data_collector.http_client'));
    }

    public function testTheDataCollectorIsDroppedWhenTheProfilerIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('data_collector.http_client'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.http_client.pool', new ChildDefinition('cache.app'));
        $container->register('cache.http_client');
        $container->register('data_collector.http_client', HttpClientDataCollector::class);

        return $container;
    }
}
