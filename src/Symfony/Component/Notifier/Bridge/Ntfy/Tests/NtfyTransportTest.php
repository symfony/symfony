<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Ntfy\NtfyTransport;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): NtfyTransport
    {
        return new NtfyTransport('test', true, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['ntfy://ntfy.sh/test', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'Symfony Notifier')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0123456789', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testCanSetCustomHost()
    {
        $transport = $this->createTransport();
        $transport->setHost($customHost = self::CUSTOM_HOST);
        $this->assertSame(\sprintf('ntfy://%s/test', $customHost), (string) $transport);
    }

    public function testCanSetCustomHostAndPort()
    {
        $transport = $this->createTransport();
        $transport->setHost($customHost = self::CUSTOM_HOST);
        $transport->setPort($customPort = self::CUSTOM_PORT);
        $this->assertSame(\sprintf('ntfy://%s:%s/test', $customHost, $customPort), (string) $transport);
    }

    public function testSend()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '2BYIwRmvBKcv', 'event' => 'message']));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $expectedBody = json_encode(['topic' => 'test', 'title' => 'Hello', 'message' => 'World']);
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new PushMessage('Hello', 'World'));

        $this->assertSame('2BYIwRmvBKcv', $sentMessage->getMessageId());
    }

    public function testSendWithPassword()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '2BYIwRmvBKcv', 'event' => 'message']));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $expectedBody = json_encode(['topic' => 'test', 'title' => 'Hello', 'message' => 'World']);
            $expectedAuthorization = 'Authorization: Bearer testtokentesttoken';
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);
            $this->assertTrue(\in_array($expectedAuthorization, $options['headers'], true));

            return $response;
        });

        $transport = $this->createTransport($client)->setPassword('testtokentesttoken');

        $sentMessage = $transport->send(new PushMessage('Hello', 'World'));

        $this->assertSame('2BYIwRmvBKcv', $sentMessage->getMessageId());
    }

    public function testSendWithUserAndPassword()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '2BYIwRmvBKcv', 'event' => 'message']));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $expectedBody = json_encode(['topic' => 'test', 'title' => 'Hello', 'message' => 'World']);
            $expectedAuthorization = 'Authorization: Basic dGVzdF91c2VyOnRlc3RfcGFzc3dvcmQ';
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);
            $this->assertTrue(\in_array($expectedAuthorization, $options['headers'], true));

            return $response;
        });

        $transport = $this->createTransport($client)->setUser('test_user')->setPassword('test_password');

        $sentMessage = $transport->send(new PushMessage('Hello', 'World'));

        $this->assertSame('2BYIwRmvBKcv', $sentMessage->getMessageId());
    }
}
