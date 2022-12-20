<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatOptions;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GoogleChatTransportTest extends TransportTestCase
{
    /**
     * @return GoogleChatTransport
     */
    public function createTransport(HttpClientInterface $client = null, string $threadKey = null): TransportInterface
    {
        return new GoogleChatTransport('My-Space', 'theAccessKey', 'theAccessToken=', $threadKey, $client ?? self::createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['googlechat://chat.googleapis.com/My-Space', $this->createTransport()];
        yield ['googlechat://chat.googleapis.com/My-Space?thread_key=abcdefg', $this->createTransport(null, 'abcdefg')];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }

    public function testSendWithEmptyArrayResponseThrowsTransportException()
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('Unable to post the Google Chat message: "[]"');
        self::expectExceptionCode(500);

        $response = self::createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('[]');

        $client = new MockHttpClient(function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        self::assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('API key not valid. Please pass a valid API key.');

        $response = self::createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('{"error":{"code":400,"message":"API key not valid. Please pass a valid API key.","status":"INVALID_ARGUMENT"}}');

        $client = new MockHttpClient(function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        self::assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithOptions()
    {
        $message = 'testMessage';

        $response = self::createMock(ResponseInterface::class);

        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('{"name":"spaces/My-Space/messages/abcdefg.hijklmno"}');

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://chat.googleapis.com/v1/spaces/My-Space/messages?key=theAccessKey&token=theAccessToken%3D&threadKey=My-Thread', $url);
            self::assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client, 'My-Thread');

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        self::assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $response = self::createMock(ResponseInterface::class);

        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('{"name":"spaces/My-Space/messages/abcdefg.hijklmno","thread":{"name":"spaces/My-Space/threads/abcdefg.hijklmno"}}');

        $notification = new Notification('testMessage');
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'text' => ' *testMessage* ',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            self::assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send($chatMessage);

        self::assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithInvalidOptions()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('The "'.GoogleChatTransport::class.'" transport only supports instances of "'.GoogleChatOptions::class.'" for options.');

        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            return self::createMock(ResponseInterface::class);
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('testMessage', self::createMock(MessageOptionsInterface::class)));
    }

    public function testSendWith200ResponseButNotOk()
    {
        $message = 'testMessage';

        self::expectException(TransportException::class);

        $response = self::createMock(ResponseInterface::class);

        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('testErrorCode');

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            self::assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        self::assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }
}
