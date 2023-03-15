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
use ProxyManager\Proxy\LazyLoadingInterface;
use ProxyManager\Proxy\ValueHolderInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests for {@see \Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @group legacy
 */
class RuntimeInstantiatorTest extends TestCase
{
    /**
     * @var RuntimeInstantiator
     */
    protected $instantiator;

    protected function setUp(): void
    {
        $this->instantiator = new RuntimeInstantiator();
    }

    public function testInstantiateProxy()
    {
        $instance = new \stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $definition = new Definition('stdClass');
        $instantiator = fn () => $instance;

        /* @var $proxy LazyLoadingInterface|ValueHolderInterface */
        $proxy = $this->instantiator->instantiateProxy($container, $definition, 'foo', $instantiator);

        $this->assertInstanceOf(LazyLoadingInterface::class, $proxy);
        $this->assertInstanceOf(ValueHolderInterface::class, $proxy);
        $this->assertFalse($proxy->isProxyInitialized());

        $proxy->initializeProxy();

        $this->assertSame($instance, $proxy->getWrappedValueHolderValue());
    }
}
