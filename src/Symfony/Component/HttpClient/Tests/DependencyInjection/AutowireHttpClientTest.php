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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Attribute\AutowireHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\Required;

class AutowireHttpClientTest extends TestCase
{
    public function testDefaultClient()
    {
        $container = $this->createContainer();

        $response = $container->get('consumer')->default->request('GET', '/items');

        $this->assertSame('https://default.example/items', $response->getInfo('url'));
        $this->assertSame(10.0, $response->getRequestOptions()['timeout']);
    }

    public function testOptionsDoNotChangeTheParentClient()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->options->request('GET', '/items');

        $this->assertSame(5.0, $response->getRequestOptions()['timeout']);
        $this->assertContains('X-Token: @literal', $response->getRequestOptions()['headers']);

        $response = $container->get('http_client')->request('GET', '/items');

        $this->assertSame(10.0, $response->getRequestOptions()['timeout']);
        $this->assertArrayNotHasKey('x-token', $response->getRequestOptions()['normalized_headers']);
    }

    public function testScopedClient()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->scoped->request('GET', 'items');

        $this->assertSame('https://api.example/v1/items', $response->getInfo('url'));
        $this->assertSame(5.0, $response->getRequestOptions()['timeout']);
        $this->assertContains('Authorization: Bearer token', $response->getRequestOptions()['headers']);
    }

    public function testOptionsDoNotApplyOutsideTheScope()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->scoped->request('GET', 'https://other.example/items');

        $this->assertSame(10.0, $response->getRequestOptions()['timeout']);
        $this->assertArrayNotHasKey('authorization', $response->getRequestOptions()['normalized_headers']);
    }

    public function testRequestOptionsOverrideTheDefaults()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->scoped->request('GET', 'items', ['timeout' => 1]);

        $this->assertSame(1.0, $response->getRequestOptions()['timeout']);
    }

    public function testCustomClientKeepsItsDecorators()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->custom->request('GET', 'items');

        $this->assertSame('https://api.example/v1/items', $response->getInfo('url'));
        $this->assertSame('custom', $response->getContent());
        $this->assertCount(1, $container->get('custom_client')->getTracedRequests());
        $this->assertSame(1, $container->get('custom_transport')->getRequestsCount());
        $this->assertSame(0, $container->get('http_client')->getRequestsCount());
    }

    public function testRequiredMethod()
    {
        $container = $this->createContainer();
        $response = $container->get('consumer')->method->request('GET', 'items');

        $this->assertSame('https://api.example/v1/items', $response->getInfo('url'));
        $this->assertContains('X-Method: @literal', $response->getRequestOptions()['headers']);
    }

    public function testEnvPlaceholdersInADumpedContainer()
    {
        $container = $this->createContainer(EnvHttpClientConsumer::class);

        eval('?>'.new PhpDumper($container)->dump(['class' => 'AutowireHttpClientTestContainer']));

        $container = new \AutowireHttpClientTestContainer();
        $response = $container->get('consumer')->client->request('GET', 'items');

        $this->assertSame('https://env.example/items', $response->getInfo('url'));
        $this->assertContains('Authorization: Bearer env-token', $response->getRequestOptions()['headers']);
    }

    private function createContainer(string $class = HttpClientConsumer::class): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('api.base_uri', 'https://api.example/v1/');
        $container->setParameter('env(AUTOWIRE_HTTP_CLIENT_URI)', 'https://env.example/');
        $container->setParameter('env(AUTOWIRE_HTTP_CLIENT_TOKEN)', 'env-token');
        $container->register('http_client', MockHttpClient::class)
            ->setArguments([null, 'https://default.example/'])
            ->addMethodCall('withOptions', [['timeout' => 10]], true)
            ->setPublic(true);
        $container->setAlias(HttpClientInterface::class, 'http_client');
        $container->register('custom_transport', MockHttpClient::class)
            ->setArguments([new Definition(MockResponse::class, ['custom'])])
            ->setPublic(true);
        $container->register('custom_client', TraceableHttpClient::class)
            ->setArguments([new Reference('custom_transport')])
            ->setPublic(true);
        $container->register('consumer', $class)->setAutowired(true)->setPublic(true);
        $container->compile();

        return $container;
    }
}

class HttpClientConsumer
{
    public HttpClientInterface $method;

    public function __construct(
        #[AutowireHttpClient]
        public HttpClientInterface $default,
        #[AutowireHttpClient(options: ['timeout' => 5, 'headers' => ['X-Token' => '@literal']])]
        public HttpClientInterface $options,
        #[AutowireHttpClient(baseUri: '%api.base_uri%', options: ['auth_bearer' => 'token', 'timeout' => 5])]
        public HttpClientInterface $scoped,
        #[AutowireHttpClient(baseUri: '%api.base_uri%', client: 'custom_client')]
        public HttpClientInterface $custom,
    ) {
    }

    #[Required]
    public function setClient(#[AutowireHttpClient(baseUri: '%api.base_uri%', options: ['headers' => ['X-Method' => '@literal']])] HttpClientInterface $client): void
    {
        $this->method = $client;
    }
}

class EnvHttpClientConsumer
{
    public function __construct(
        #[AutowireHttpClient(baseUri: '%env(AUTOWIRE_HTTP_CLIENT_URI)%', options: ['auth_bearer' => '%env(AUTOWIRE_HTTP_CLIENT_TOKEN)%'])]
        public HttpClientInterface $client,
    ) {
    }
}
