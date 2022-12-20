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

use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\LazyLoadingInterface;
use ProxyManagerBridgeFooClass;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Integration tests for {@see \Symfony\Component\DependencyInjection\ContainerBuilder} combined
 * with the ProxyManager bridge.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ContainerBuilderTest extends TestCase
{
    public function testCreateProxyServiceWithRuntimeInstantiator()
    {
        $builder = new ContainerBuilder();

        $builder->setProxyInstantiator(new RuntimeInstantiator());

        $builder->register('foo1', ProxyManagerBridgeFooClass::class)->setFile(__DIR__.'/Fixtures/includes/foo.php')->setPublic(true);
        $builder->getDefinition('foo1')->setLazy(true);

        $builder->compile();

        /* @var $foo1 \ProxyManager\Proxy\LazyLoadingInterface|\ProxyManager\Proxy\ValueHolderInterface */
        $foo1 = $builder->get('foo1');

        $foo1->__destruct();
        self::assertSame(0, $foo1::$destructorCount);

        self::assertSame($foo1, $builder->get('foo1'), 'The same proxy is retrieved on multiple subsequent calls');
        self::assertInstanceOf(ProxyManagerBridgeFooClass::class, $foo1);
        self::assertInstanceOf(LazyLoadingInterface::class, $foo1);
        self::assertFalse($foo1->isProxyInitialized());

        $foo1->initializeProxy();

        self::assertSame($foo1, $builder->get('foo1'), 'The same proxy is retrieved after initialization');
        self::assertTrue($foo1->isProxyInitialized());
        self::assertInstanceOf(ProxyManagerBridgeFooClass::class, $foo1->getWrappedValueHolderValue());
        self::assertNotInstanceOf(LazyLoadingInterface::class, $foo1->getWrappedValueHolderValue());

        $foo1->__destruct();
        self::assertSame(1, $foo1::$destructorCount);
    }
}
