<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\ProxyManager\Tests\LazyProxy\Instantiator;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symphony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symphony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RuntimeInstantiatorTest extends TestCase
{
    /**
     * @var RuntimeInstantiator
     */
    protected $instantiator;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->instantiator = new RuntimeInstantiator();
    }

    public function testInstantiateProxy()
    {
        $instance = new \stdClass();
        $container = $this->getMockBuilder('Symphony\Component\DependencyInjection\ContainerInterface')->getMock();
        $definition = new Definition('stdClass');
        $instantiator = function () use ($instance) {
            return $instance;
        };

        /* @var $proxy \ProxyManager\Proxy\LazyLoadingInterface|\ProxyManager\Proxy\ValueHolderInterface */
        $proxy = $this->instantiator->instantiateProxy($container, $definition, 'foo', $instantiator);

        $this->assertInstanceOf('ProxyManager\Proxy\LazyLoadingInterface', $proxy);
        $this->assertInstanceOf('ProxyManager\Proxy\ValueHolderInterface', $proxy);
        $this->assertFalse($proxy->isProxyInitialized());

        $proxy->initializeProxy();

        $this->assertSame($instance, $proxy->getWrappedValueHolderValue());
    }
}
