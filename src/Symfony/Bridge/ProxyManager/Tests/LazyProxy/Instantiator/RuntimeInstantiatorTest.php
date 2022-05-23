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
use ProxyManager\Proxy\GhostObjectInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    protected function setUp(): void
    {
        $this->instantiator = new RuntimeInstantiator();
    }

    public function testInstantiateProxy()
    {
        $container = $this->createMock(ContainerInterface::class);
        $definition = new Definition('stdClass');
        $instantiator = function ($proxy) {
            return $proxy;
        };

        /* @var $proxy GhostObjectInterface */
        $proxy = $this->instantiator->instantiateProxy($container, $definition, 'foo', $instantiator);

        $this->assertInstanceOf(GhostObjectInterface::class, $proxy);
        $this->assertFalse($proxy->isProxyInitialized());

        $proxy->initializeProxy();
        $this->assertTrue($proxy->isProxyInitialized());
    }
}
