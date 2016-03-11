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
use Symfony\Component\DependencyInjection\Reference;

class CachePoolPassTest extends \PHPUnit_Framework_TestCase
{
    private $cachePoolPass;

    protected function setUp()
    {
        $this->cachePoolPass = new CachePoolPass();
    }

    public function testAdapterIsInjectedIntoConstructorArguments()
    {
        $container = $this->initializeContainer();
        $this->cachePoolPass->process($container);
        $adapter = $container->getDefinition('foo')->getArgument(0);

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\DefinitionDecorator', $adapter);
        $this->assertFalse($adapter->isAbstract());
        $this->assertSame('cache.adapter.apcu_adapter', $adapter->getParent());
        $this->assertSame('0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', $adapter->getArgument(0));
    }

    public function testAdapterIsInjectedIntoMethodArguments()
    {
        $container = $this->initializeContainer();
        $this->cachePoolPass->process($container);
        $methodCalls = $container->getDefinition('bar')->getMethodCalls();
        $arguments = $methodCalls[0][1];
        $adapter = $arguments[0];

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\DefinitionDecorator', $adapter);
        $this->assertFalse($adapter->isAbstract());
        $this->assertSame('cache.adapter.doctrine_adapter', $adapter->getParent());
    }

    public function testAdapterIsInjectIntoProperties()
    {
        $container = $this->initializeContainer();
        $this->cachePoolPass->process($container);
        $properties = $container->getDefinition('baz')->getProperties();
        $adapter = $properties['cache'];

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\DefinitionDecorator', $adapter);
        $this->assertFalse($adapter->isAbstract());
        $this->assertSame('cache.adapter.fs_adapter', $adapter->getParent());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The cache adapter "bar" is not configured
     */
    public function testThrowsExceptionWhenReferencedAdapterIsNotConfigured()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('foo', new Definition('Foo', array(new Reference('cache.adapter.bar'))));
        $this->cachePoolPass->process($container);
    }

    private function initializeContainer()
    {
        $container = new ContainerBuilder();

        $apcuAdapter = new Definition('Symfony\Component\Cache\Adapter\ApcuAdapter');
        $apcuAdapter->setAbstract(true);
        $apcuAdapter->addTag('cache.adapter', array('id' => 'adapter1', 'namespace-arg-index' => 0));
        $container->setDefinition('cache.adapter.apcu_adapter', $apcuAdapter);

        $doctrineAdapter = new Definition('Symfony\Component\Cache\Adapter\DoctrineAdapter');
        $doctrineAdapter->setAbstract(true);
        $doctrineAdapter->addTag('cache.adapter', array('id' => 'adapter2'));
        $container->setDefinition('cache.adapter.doctrine_adapter', $doctrineAdapter);

        $filesystemAdapter = new Definition('Symfony\Component\Cache\Adapter\FilesystemAdapter');
        $filesystemAdapter->setAbstract(true);
        $filesystemAdapter->addTag('cache.adapter', array('id' => 'adapter3'));
        $container->setDefinition('cache.adapter.fs_adapter', $filesystemAdapter);

        $foo = new Definition();
        $foo->setArguments(array(new Reference('cache.adapter.adapter1')));
        $container->setDefinition('foo', $foo);

        $bar = new Definition();
        $bar->addMethodCall('setCache', array(new Reference('cache.adapter.adapter2')));
        $container->setDefinition('bar', $bar);

        $baz = new Definition();
        $baz->setProperty('cache', new Reference('cache.adapter.adapter3'));
        $container->setDefinition('baz', $baz);

        return $container;
    }
}
