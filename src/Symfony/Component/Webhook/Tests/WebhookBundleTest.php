<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Subscriber;
use Symfony\Component\Webhook\Tests\Fixtures\TestConsumer;
use Symfony\Component\Webhook\Tests\Fixtures\TestRequestParser;
use Symfony\Component\Webhook\WebhookBundle;

class WebhookBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_webhook_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testIncomingWebhooksReachTheirConsumer()
    {
        $kernel = new TestWebhookKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $request = Request::create('/webhook/test', 'POST', server: ['HTTP_X_SECRET' => 'the-secret'], content: '{"foo":"bar"}');
        $response = $container->get('webhook.controller')->handle('test', $request);

        $this->assertSame(202, $response->getStatusCode());

        $events = $container->get('test.consumer')->events;
        $this->assertCount(1, $events);
        $this->assertSame('parsed', $events[0]->getName());
        $this->assertSame(['foo' => 'bar'], $events[0]->getPayload());
    }

    public function testWebhooksAreSentWithSignedHeaders()
    {
        $kernel = new TestWebhookKernel('send', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $container->get('test.webhook.transport')->send(new Subscriber('https://example.com/hook', 'the-secret'), new RemoteEvent('the-event', 'the-id', ['foo' => 'bar']));

        $options = $container->get('test.mock_response')->getRequestOptions();
        $headers = [];
        foreach ($options['headers'] as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $headers[strtolower($name)] = $value;
        }

        $this->assertSame('the-event', $headers['webhook-event']);
        $this->assertSame('the-id', $headers['webhook-id']);
        $this->assertSame('{"foo":"bar"}', $options['body']);
        $this->assertSame('sha256='.hash_hmac('sha256', 'the-eventthe-id{"foo":"bar"}', 'the-secret'), $headers['webhook-signature']);
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new WebhookBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('webhook.controller'));
        $this->assertFalse($container->hasDefinition('webhook.transport'));
        $this->assertFalse($container->hasDefinition('webhook.request_parser'));
    }
}

class TestWebhookKernel extends AbstractKernel
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
        yield new WebhookBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('webhook', [
            'routing' => [
                'test' => ['service' => 'test.request_parser', 'secret' => 'the-secret'],
            ],
        ]);

        $container->services()
            ->set('test.request_parser', TestRequestParser::class)
            ->set('test.consumer', TestConsumer::class)->autoconfigure()->public()
            ->set('test.mock_response', MockResponse::class)->public()
            ->set('http_client', MockHttpClient::class)->args([new Reference('test.mock_response')])
            ->alias('test.webhook.transport', 'webhook.transport')->public()
        ;
    }
}
