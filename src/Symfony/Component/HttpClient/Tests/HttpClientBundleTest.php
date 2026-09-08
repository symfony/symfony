<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClientBundle;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_http_client_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheDefaultClientIsRegistered()
    {
        $kernel = new TestHttpClientKernel('default', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $client = $container->get('test.client');
        $this->assertInstanceOf(HttpClientInterface::class, $client);

        $this->assertSame('{"foo":"bar"}', $client->request('GET', 'https://example.com/')->getContent());
    }

    public function testScopedClientsAreRegistered()
    {
        $kernel = new TestHttpClientKernel('scoped', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $response = $container->get('test.scoped_client')->request('GET', '/path');
        $this->assertSame('https://example.com/path', $response->getInfo('url'));
        $this->assertContains('X-Scoped: yes', $response->getRequestOptions()['headers']);

        $this->assertSame($container->get('test.scoped_client'), $container->get('test.autowired_scoped_client')->client);
    }

    public function testTheClientsAreTracedInDebugMode()
    {
        $kernel = new TestHttpClientKernel('debug', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $collector = $container->get('test.data_collector');
        $container->get('test.client')->request('GET', 'https://example.com/')->getContent();
        $collector->lateCollect();

        $this->assertSame(1, $collector->getRequestCount());
        $this->assertSame(['http_client', 'scoped_client', 'cached_client'], array_keys($collector->getClients()));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new HttpClientBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('http_client'));
        $this->assertFalse($container->hasDefinition('http_client.transport'));
        $this->assertFalse($container->hasDefinition('cache.http_client'));
    }
}

class TestHttpClientKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new HttpClientBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('http_client', [
            'mock_response_factory' => 'test.mock_factory',
            'scoped_clients' => [
                'scoped_client' => [
                    'base_uri' => 'https://example.com/',
                    'headers' => ['X-Scoped' => 'yes'],
                ],
                'cached_client' => [
                    'base_uri' => 'https://example.com/',
                    'caching' => ['cache_pool' => 'test.cache_pool'],
                ],
            ],
        ]);

        $services = $container->services();
        $services
            ->set('test.mock_factory', MockResponseFactory::class)
            ->set('test.cache_pool', ArrayAdapter::class)
            ->set('logger', NullLogger::class)
            ->set('test.autowired_scoped_client', AutowiredScopedClient::class)
                ->autowire()
                ->public()
            ->alias('test.client', 'http_client')->public()
            ->alias('test.scoped_client', 'scoped_client')->public()
            ->alias('test.cached_client', 'cached_client')->public()
        ;

        if ($this->debug) {
            // the data collector is kept only when a profiler collects it
            $services->set('profiler', \stdClass::class);
            $services->alias('test.data_collector', 'data_collector.http_client')->public();
        }
    }
}

class AutowiredScopedClient
{
    public function __construct(
        #[Target('scoped_client')]
        public HttpClientInterface $client,
    ) {
    }
}

class MockResponseFactory
{
    public function __invoke(): MockResponse
    {
        return new MockResponse('{"foo":"bar"}');
    }
}
