<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SlackTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, string $channel = null): SlackTransport
    {
        return new SlackTransport('xoxb-TestToken', $channel, $client ?? $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['slack://slack.com', $this->createTransport()];
        yield ['slack://slack.com?channel=test+Channel', $this->createTransport(null, 'test Channel')];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testInstatiatingWithAnInvalidSlackTokenThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid Slack token needs to start with "xoxb-", "xoxp-" or "xoxa-2". See https://api.slack.com/authentication/token-types for further information.');

        new SlackTransport('token', 'testChannel', $this->createMock(HttpClientInterface::class));
    }

    public function testSendWithEmptyArrayResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('[]');

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client, 'testChannel');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testErrorCode/');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['error' => 'testErrorCode']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client, 'testChannel');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['ok' => true, 'ts' => '1503435956.000247']));

        $expectedBody = json_encode(['channel' => $channel, 'text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client, $channel);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('1503435956.000247', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['ok' => true, 'ts' => '1503435956.000247']));

        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);
        $options = SlackOptions::fromNotification($notification);

        $expectedBody = json_encode([
            'blocks' => $options->toArray()['blocks'],
            'channel' => $channel,
            'text' => $message,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client, $channel);

        $sentMessage = $transport->send($chatMessage);

        $this->assertSame('1503435956.000247', $sentMessage->getMessageId());
    }

    public function testSendWithInvalidOptions()
    {
        $this->expectException(LogicException::class);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        });

        $transport = $this->createTransport($client, 'testChannel');

        $transport->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }

    public function testSendWith200ResponseButNotOk()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $this->expectException(TransportException::class);

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['ok' => false, 'error' => 'testErrorCode']));

        $expectedBody = json_encode(['channel' => $channel, 'text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client, $channel);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendIncludesContentTypeWithCharset()
    {
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['ok' => true, 'ts' => '1503435956.000247']));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $this->assertContains('Content-Type: application/json; charset=utf-8', $options['headers']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorsIncluded()
    {
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'ok' => false,
                'error' => 'invalid_blocks',
                'errors' => ['no more than 50 items allowed [json-pointer:/blocks]'],
            ]));

        $client = new MockHttpClient(function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client, 'testChannel');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to post the Slack message: "invalid_blocks" (no more than 50 items allowed [json-pointer:/blocks]).');

        $transport->send(new ChatMessage('testMessage'));
    }
}
