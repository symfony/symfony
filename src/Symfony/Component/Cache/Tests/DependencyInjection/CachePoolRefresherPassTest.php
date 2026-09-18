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
use Symfony\Component\Cache\CachePoolRefresher;
use Symfony\Component\Cache\DependencyInjection\CachePoolRefresherPass;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CachePoolRefresherPassTest extends TestCase
{
    public function testOnlyThePoolsThatOptedInAreCollected()
    {
        $container = $this->containerWithRefresher();
        $container->register('pool.in', FilesystemAdapter::class)->addTag('cache.pool', ['refreshable' => true]);
        $container->register('pool.out', FilesystemAdapter::class)->addTag('cache.pool', ['refreshable' => false]);
        $container->register('pool.silent', FilesystemAdapter::class)->addTag('cache.pool');

        (new CachePoolRefresherPass())->process($container);

        $this->assertSame(['pool.in'], array_keys($this->collected($container)));
    }

    public function testTheUserFacingPoolIsCollectedRatherThanTheTaggedOne()
    {
        // a taggable pool carries the tag on its inner adapter, while reads and writes
        // go through the TagAwareAdapter registered under the pool name
        $container = $this->containerWithRefresher();
        $container->register('.pool.tagged.inner', FilesystemAdapter::class)
            ->addTag('cache.pool', ['refreshable' => true, 'name' => 'pool.tagged']);

        (new CachePoolRefresherPass())->process($container);

        $this->assertEquals(['pool.tagged' => new Reference('pool.tagged')], $this->collected($container));
    }

    public function testItSkipsTheAbstractAdapterTemplates()
    {
        $container = $this->containerWithRefresher();
        $container->register('cache.adapter.fs', FilesystemAdapter::class)->setAbstract(true)->addTag('cache.pool', ['refreshable' => true]);

        (new CachePoolRefresherPass())->process($container);

        $this->assertSame([], $this->collected($container));
    }

    public function testAPoolThatCannotRefreshIsRejected()
    {
        $container = $this->containerWithRefresher();
        $container->register('pool.plain', NotRefreshableAdapter::class)->addTag('cache.pool', ['refreshable' => true]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache pool "pool.plain" is marked as refreshable but its class');

        (new CachePoolRefresherPass())->process($container);
    }

    public function testItIsIgnoredWhenTheServiceIsGone()
    {
        $container = new ContainerBuilder();
        $container->register('pool.in', FilesystemAdapter::class)->addTag('cache.pool', ['refreshable' => true]);

        $definitionsBefore = \count($container->getDefinitions());

        (new CachePoolRefresherPass())->process($container);

        $this->assertCount($definitionsBefore, $container->getDefinitions());
    }

    private function containerWithRefresher(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('cache.refresher', CachePoolRefresher::class)->addArgument(new IteratorArgument([]));

        return $container;
    }

    private function collected(ContainerBuilder $container): array
    {
        $argument = $container->getDefinition('cache.refresher')->getArgument(0);

        $this->assertInstanceOf(IteratorArgument::class, $argument);

        return $argument->getValues();
    }
}

class NotRefreshableAdapter
{
}
