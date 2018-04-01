<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CacheCollectorPass;
use Symphony\Component\Cache\Adapter\FilesystemAdapter;
use Symphony\Component\Cache\Adapter\TagAwareAdapter;
use Symphony\Component\Cache\Adapter\TraceableAdapter;
use Symphony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symphony\Component\Cache\DataCollector\CacheDataCollector;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

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

        $collector = $container->register('data_collector.cache', CacheDataCollector::class);
        (new CacheCollectorPass())->process($container);

        $this->assertEquals(array(
            array('addInstance', array('fs', new Reference('fs'))),
            array('addInstance', array('tagged_fs', new Reference('tagged_fs'))),
        ), $collector->getMethodCalls());

        $this->assertSame(TraceableAdapter::class, $container->findDefinition('fs')->getClass());
        $this->assertSame(TraceableTagAwareAdapter::class, $container->getDefinition('tagged_fs')->getClass());
        $this->assertFalse($collector->isPublic(), 'The "data_collector.cache" should be private after processing');
    }
}
