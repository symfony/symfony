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
use Symfony\Component\DependencyInjection\Reference;

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
        $adapter->addTag('cache.pool');
        $container->setDefinition('app.cache_adapter', $adapter);
        $container->setAlias('app.cache_adapter_alias', 'app.cache_adapter');
        $cachePool = new DefinitionDecorator('app.cache_adapter_alias');
        $cachePool->addArgument(null);
        $cachePool->addTag('cache.pool');
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertSame('VcRIZlUhEv', $cachePool->getArgument(0));
    }

    public function testArgsAreReplaced()
    {
        $container = new ContainerBuilder();
        $cachePool = new Definition();
        $cachePool->addTag('cache.pool', array(
            'provider' => 'foobar',
            'default_lifetime' => 3,
        ));
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $cachePool->addArgument(null);
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);

        $this->assertInstanceOf(Reference::class, $cachePool->getArgument(0));
        $this->assertSame('foobar', (string) $cachePool->getArgument(0));
        $this->assertSame('VcRIZlUhEv', $cachePool->getArgument(1));
        $this->assertSame(3, $cachePool->getArgument(2));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid "cache.pool" tag for service "app.cache_pool": accepted attributes are
     */
    public function testThrowsExceptionWhenCachePoolTagHasUnknownAttributes()
    {
        $container = new ContainerBuilder();
        $adapter = new Definition();
        $adapter->setAbstract(true);
        $adapter->addTag('cache.pool');
        $container->setDefinition('app.cache_adapter', $adapter);
        $cachePool = new DefinitionDecorator('app.cache_adapter');
        $cachePool->addTag('cache.pool', array('foobar' => 123));
        $container->setDefinition('app.cache_pool', $cachePool);

        $this->cachePoolPass->process($container);
    }
}
