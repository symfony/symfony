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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SlackTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();
        $transport->setHost('host.test');

        $this->assertSame('slack://host.test/testPath', (string) $transport);
    }

    public function testSupportsChatMessage()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithEmptyArrayResponseThrows()
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

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('testErrorCode');

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('testErrorCode');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('ok');

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage($message));
    }

    public function testSendWithNotification()
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('ok');

        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);
        $options = SlackOptions::fromNotification($notification);

        $expectedBody = json_encode([
            'blocks' => $options->toArray()['blocks'],
            'text' => $message,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $transport->send($chatMessage);
    }

    public function testSendWithInvalidOptions()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        });

        $transport = $this->createTransport($client);

        $this->expectException(LogicException::class);

        $transport->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }

    public function testSendWith200ResponseButNotOk()
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('testErrorCode');

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);

        $transport->send(new ChatMessage($message));
    }

    private function createTransport(?HttpClientInterface $client = null): SlackTransport
    {
        return (new SlackTransport('testPath', $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
