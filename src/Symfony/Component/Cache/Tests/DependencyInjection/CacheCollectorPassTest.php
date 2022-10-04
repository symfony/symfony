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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CacheCollectorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('fs', FilesystemAdapter::class)
            ->addMethodCall('setCallbackWrapper', [(new Definition())
                ->addArgument(null)
                ->addArgument(null)
                ->addArgument((new Definition('callable'))
                    ->setFactory([new Reference('fs'), 'setCallbackWrapper'])
                ),
            ])
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
            ['addInstance', ['php', new Reference('.php.inner')]],
        ], $collector->getMethodCalls());

        $this->assertSame(TraceableAdapter::class, $container->findDefinition('fs')->getClass());
        $this->assertSame(TraceableTagAwareAdapter::class, $container->getDefinition('tagged_fs')->getClass());

        $this->assertSame(TraceableAdapter::class, $container->findDefinition('.php.inner')->getClass());
        $this->assertSame(TagAwareAdapter::class, $container->getDefinition('php')->getClass());

        $this->assertFalse($collector->isPublic(), 'The "data_collector.cache" should be private after processing');

        $innerFs = $container->getDefinition('fs.recorder_inner');
        $this->assertEquals([new Reference('fs.recorder_inner'), 'setCallbackWrapper'], $innerFs->getMethodCalls()[0][1][0]->getArgument(2)->getFactory());
    }

    public function testProcessCacheObjectsAreDecorated()
    {
        $container = new ContainerBuilder();
        $collector = $container->register('data_collector.cache', CacheDataCollector::class);

        $container
            ->register('cache.object', ArrayAdapter::class)
            ->addTag('cache.pool', ['name' => 'cache.object']);

        $container
            ->register('something_is_decorating_cache_object', TagAwareAdapter::class)
            ->setPublic(true)
            ->setDecoratedService('cache.object');

        $container->register('some_service_using_cache_object', TraceableAdapter::class)
            ->setPublic(true)
            ->addArgument(new Reference('cache.object'));

        $container->addCompilerPass(new CacheCollectorPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $container->compile();
        $this->assertCount(1, $collector->getMethodCalls());
        $this->assertEquals(
            [
                [
                    'addInstance',
                    [
                        'cache.object',
                        new Reference('something_is_decorating_cache_object'),
                    ],
                ],
            ],
            $collector->getMethodCalls()
        );
    }
}
