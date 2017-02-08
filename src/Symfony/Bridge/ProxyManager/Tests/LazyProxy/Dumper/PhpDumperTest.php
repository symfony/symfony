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
        $container = new ContainerBuilder();

        $container->register('foo', 'stdClass');
        $container->getDefinition('foo')->setLazy(true);
        $container->compile();

        $dumper = new PhpDumper($container);

        $dumper->setProxyDumper(new ProxyDumper());

        $dumpedString = $dumper->dump();

        $this->assertStringMatchesFormatFile(
            __DIR__.'/../Fixtures/php/lazy_service_structure.txt',
            $dumpedString,
            '->dump() does generate proxy lazy loading logic.'
        );
    }

    /**
     * Verifies that the generated container retrieves the same proxy instance on multiple subsequent requests.
     */
    public function testDumpContainerWithProxyServiceWillShareProxies()
    {
        // detecting ProxyManager v2
        if (class_exists('ProxyManager\ProxyGenerator\LazyLoading\MethodGenerator\StaticProxyConstructor')) {
            require_once __DIR__.'/../Fixtures/php/lazy_service_with_hints.php';
        } else {
            require_once __DIR__.'/../Fixtures/php/lazy_service.php';
        }

        $container = new \LazyServiceProjectServiceContainer();

        /* @var $proxy \stdClass_c1d194250ee2e2b7d2eab8b8212368a8 */
        $proxy = $container->get('foo');
        $this->assertInstanceOf('stdClass_c1d194250ee2e2b7d2eab8b8212368a8', $proxy);
        $this->assertSame($proxy, $container->get('foo'));

        $this->assertFalse($proxy->isProxyInitialized());

        $proxy->initializeProxy();

        $this->assertTrue($proxy->isProxyInitialized());
        $this->assertSame($proxy, $container->get('foo'));
    }
}
