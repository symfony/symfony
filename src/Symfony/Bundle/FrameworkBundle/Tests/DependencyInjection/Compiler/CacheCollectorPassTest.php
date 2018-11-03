<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CacheCollectorPass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @group legacy
 */
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
