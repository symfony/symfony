<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Messenger\PingWebhookMessage;
use Symfony\Component\HttpClient\Messenger\PingWebhookMessageHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class PingWebhookMessageHandlerTest extends TestCase
{
    public function testSuccessfulPing()
    {
        $client = new MockHttpClient([
            function ($method, $url) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://endpoint.com/key', $url);

                return new MockResponse('a response');
            },
        ]);
        $handler = new PingWebhookMessageHandler($client);
        $response = $handler(new PingWebhookMessage('POST', 'https://endpoint.com/key'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('a response', $response->getContent());
        $this->assertSame('https://endpoint.com/key', $response->getInfo('url'));
    }

    public function testPingErrorThrowsException()
    {
        $client = new MockHttpClient([
            function ($method, $url) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://endpoint.com/key', $url);

                return new MockResponse('a response', ['http_code' => 404]);
            },
        ]);

        $handler = new PingWebhookMessageHandler($client);

        $this->expectException(ClientException::class);

        $handler(new PingWebhookMessage('POST', 'https://endpoint.com/key'));
    }

    public function testPingErrorDoesNotThrowException()
    {
        $client = new MockHttpClient([
            function ($method, $url) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://endpoint.com/key', $url);

                return new MockResponse('a response', ['http_code' => 404]);
            },
        ]);

        $handler = new PingWebhookMessageHandler($client);
        $response = $handler(new PingWebhookMessage('POST', 'https://endpoint.com/key', throw: false));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('a response', $response->getContent(false));
        $this->assertSame('https://endpoint.com/key', $response->getInfo('url'));
    }
}
