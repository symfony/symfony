<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Tests\LazyProxy;

require_once __DIR__.'/Fixtures/includes/foo.php';

use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Integration tests for {@see \Symfony\Component\DependencyInjection\ContainerBuilder} combined
 * with the ProxyManager bridge.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ContainerBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateProxyServiceWithRuntimeInstantiator()
    {
        $builder = new ContainerBuilder();

        $builder->setProxyInstantiator(new RuntimeInstantiator());

        $builder->register('foo1', 'ProxyManagerBridgeFooClass')->setFile(__DIR__.'/Fixtures/includes/foo.php');
        $builder->getDefinition('foo1')->setLazy(true);

        $builder->compile();

        /* @var $foo1 \ProxyManager\Proxy\LazyLoadingInterface|\ProxyManager\Proxy\ValueHolderInterface */
        $foo1 = $builder->get('foo1');

        $this->assertSame($foo1, $builder->get('foo1'), 'The same proxy is retrieved on multiple subsequent calls');
        $this->assertInstanceOf('\ProxyManagerBridgeFooClass', $foo1);
        $this->assertInstanceOf('\ProxyManager\Proxy\LazyLoadingInterface', $foo1);
        $this->assertFalse($foo1->isProxyInitialized());

        $foo1->initializeProxy();

        $this->assertSame($foo1, $builder->get('foo1'), 'The same proxy is retrieved after initialization');
        $this->assertTrue($foo1->isProxyInitialized());
        $this->assertInstanceOf('\ProxyManagerBridgeFooClass', $foo1->getWrappedValueHolderValue());
        $this->assertNotInstanceOf('\ProxyManager\Proxy\LazyLoadingInterface', $foo1->getWrappedValueHolderValue());
    }
}
