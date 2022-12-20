<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\DependencyInjection\HttpClientPass;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientPassTest extends TestCase
{
    public function testItRequiresDataCollector()
    {
        $container = $this->buildContainerBuilder('http_client');
        $sut = new HttpClientPass();
        $sut->process($container);

        self::assertFalse($container->hasDefinition('.debug.http_client'));
    }

    public function testItDecoratesHttpClientWithTraceableHttpClient()
    {
        $container = $this->buildContainerBuilder('foo');
        $container->register('data_collector.http_client', HttpClientDataCollector::class);
        $sut = new HttpClientPass();
        $sut->process($container);
        self::assertTrue($container->hasDefinition('.debug.foo'));
        self::assertSame(TraceableHttpClient::class, $container->getDefinition('.debug.foo')->getClass());
        self::assertSame(['foo', null, 5], $container->getDefinition('.debug.foo')->getDecoratedService());
    }

    public function testItRegistersDebugHttpClientToCollector()
    {
        $container = $this->buildContainerBuilder('foo_client');
        $container->register('data_collector.http_client', HttpClientDataCollector::class);
        $sut = new HttpClientPass();
        $sut->process($container);
        self::assertEquals([['registerClient', ['foo_client', new Reference('.debug.foo_client')]]], $container->getDefinition('data_collector.http_client')->getMethodCalls());
    }

    private function buildContainerBuilder(string $clientId = 'http_client'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $container->register($clientId, HttpClientInterface::class)->addTag('http_client.client')->setArgument(0, []);

        return $container;
    }
}
