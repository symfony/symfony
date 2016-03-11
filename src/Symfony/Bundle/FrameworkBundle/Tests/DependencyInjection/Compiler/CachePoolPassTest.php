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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

class CachePoolPassTest extends \PHPUnit_Framework_TestCase
{
    private $cachePoolPass;

    protected function setUp()
    {
        $this->cachePoolPass = new CachePoolPass();
    }

    public function testNamespaceArgumentIsReplaced()
    {
        $container = new ContainerBuilder();
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.adapter', array('namespace_arg_index' => 0));
        $container->setDefinition('app.cache_adapter', $adapter);
        $cachePool = new DefinitionDecorator('app.cache_adapter');
        $cachePool->addArgument(null);
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('yRnzIIVLvL', $cachePool->getArgument(0));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Services tagged with "cache.pool" must have a parent service but "app.cache_pool" has none.
     */
    public function testThrowsExceptionWhenCachePoolHasNoParentDefinition()
    {
        $container = new ContainerBuilder();
        $cachePool = new Definition();
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Services tagged with "cache.pool" must have a parent service tagged with "cache.adapter" but "app.cache_pool" has none.
     */
    public function testThrowsExceptionWhenCachePoolIsNotBasedOnAdapter()
    {
        $container = new ContainerBuilder();
        $container->register('app.cache_adapter');
        $cachePool = new DefinitionDecorator('app.cache_adapter');
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid "cache.adapter" tag for service "app.cache_adapter": attribute "namespace_arg_index" is missing.
     */
    public function testThrowsExceptionWhenCacheAdapterDefinesNoNamespaceArgument()
    {
        $container = new ContainerBuilder();
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.adapter');
        $container->setDefinition('app.cache_adapter', $adapter);
        $cachePool = new DefinitionDecorator('app.cache_adapter');
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Services tagged as "cache.adapter" must be abstract: "app.cache_adapter" is not.
     */
    public function testThrowsExceptionWhenCacheAdapterIsNotAbstract()
    {
        $container = new ContainerBuilder();
        $adapter = new Definition();
        $adapter->addTag('cache.adapter', array('namespace_arg_index' => 0));
        $container->setDefinition('app.cache_adapter', $adapter);
        $cachePool = new DefinitionDecorator('app.cache_adapter');
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }
}
