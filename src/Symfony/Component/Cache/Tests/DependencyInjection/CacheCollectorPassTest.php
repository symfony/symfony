<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CacheCollectorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('fs', FilesystemAdapter::class)
            ->addTag('cache.pool');
        $container
            ->register('tagged_fs', TagAwareAdapter::class)
            ->addArgument(new Reference('fs'))
            ->addTag('cache.pool');

        $container
            ->register('.php.inner', PhpArrayAdapter::class)
            ->addTag('cache.pool', ['name' => 'php']);
        $container
            ->register('php', TagAwareAdapter::class)
            ->addArgument(new Reference('.php.inner'));

        $collector = $container->register('data_collector.cache', CacheDataCollector::class);
        (new CacheCollectorPass())->process($container);

        $this->assertEquals([
            ['addInstance', ['fs', new Reference('fs')]],
            ['addInstance', ['tagged_fs', new Reference('tagged_fs')]],
            ['addInstance', ['.php.inner', new Reference('.php.inner')]],
            ['addInstance', ['php', new Reference('php')]],
        ], $collector->getMethodCalls());

        $this->assertSame(TraceableAdapter::class, $container->findDefinition('fs')->getClass());
        $this->assertSame(TraceableTagAwareAdapter::class, $container->getDefinition('tagged_fs')->getClass());

        $this->assertSame(TraceableAdapter::class, $container->findDefinition('.php.inner')->getClass());
        $this->assertSame(TraceableTagAwareAdapter::class, $container->getDefinition('php')->getClass());

        $this->assertFalse($collector->isPublic(), 'The "data_collector.cache" should be private after processing');
    }
}
