<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Tests\LazyProxy\Dumper;

use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\GhostObjectInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * Integration tests for {@see \Symfony\Component\DependencyInjection\Dumper\PhpDumper} combined
 * with the ProxyManager bridge.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class PhpDumperTest extends TestCase
{
    public function testDumpContainerWithProxyService()
    {
        $this->assertStringMatchesFormatFile(
            __DIR__.'/../Fixtures/php/lazy_service_structure.txt',
            $this->dumpLazyServiceProjectServiceContainer(),
            '->dump() does generate proxy lazy loading logic.'
        );
    }

    /**
     * Verifies that the generated container retrieves the same proxy instance on multiple subsequent requests.
     */
    public function testDumpContainerWithProxyServiceWillShareProxies()
    {
        if (!class_exists(\LazyServiceProjectServiceContainer::class, false)) {
            eval('?>'.$this->dumpLazyServiceProjectServiceContainer());
        }

        $container = new \LazyServiceProjectServiceContainer();

        $proxy = $container->get('foo');
        $this->assertInstanceOf(\stdClass::class, $proxy);
        $this->assertInstanceOf(GhostObjectInterface::class, $proxy);
        $this->assertSame($proxy, $container->get('foo'));

        $this->assertFalse($proxy->isProxyInitialized());

        $proxy->initializeProxy();

        $this->assertTrue($proxy->isProxyInitialized());
        $this->assertSame($proxy, $container->get('foo'));
    }

    private function dumpLazyServiceProjectServiceContainer()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'stdClass')
            ->setPublic(true)
            ->setLazy(true)
            ->setProperty('bar', 123);
        $container->compile();

        $dumper = new PhpDumper($container);

        $dumper->setProxyDumper(new ProxyDumper());

        return $dumper->dump(['class' => 'LazyServiceProjectServiceContainer']);
    }
}
