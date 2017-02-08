<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Tests\LazyProxy\Instantiator;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator}.
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
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
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
