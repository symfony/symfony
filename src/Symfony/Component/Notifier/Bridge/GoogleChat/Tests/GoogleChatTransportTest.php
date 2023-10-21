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
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GoogleChatTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $threadKey = null): GoogleChatTransport
    {
        return new GoogleChatTransport('My-Space', 'theAccessKey', 'theAccessToken=', $threadKey, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['googlechat://chat.googleapis.com/My-Space', self::createTransport()];
        yield ['googlechat://chat.googleapis.com/My-Space?thread_key=abcdefg', self::createTransport(null, 'abcdefg')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithEmptyArrayResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to post the Google Chat message: "[]"');
        $this->expectExceptionCode(500);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('[]');

        $client = new MockHttpClient(fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('API key not valid. Please pass a valid API key.');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"error":{"code":400,"message":"API key not valid. Please pass a valid API key.","status":"INVALID_ARGUMENT"}}');

        $client = new MockHttpClient(fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
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
            ->willReturn('{"name":"spaces/My-Space/messages/abcdefg.hijklmno"}');

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://chat.googleapis.com/v1/spaces/My-Space/messages?key=theAccessKey&token=theAccessToken%3D&threadKey=My-Thread', $url);
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = self::createTransport($client, 'My-Thread');

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"name":"spaces/My-Space/messages/abcdefg.hijklmno","thread":{"name":"spaces/My-Space/threads/abcdefg.hijklmno"}}');

        $notification = new Notification('testMessage');
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'text' => ' *testMessage* ',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = self::createTransport($client);

        $sentMessage = $transport->send($chatMessage);

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithInvalidOptions()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "'.GoogleChatTransport::class.'" transport only supports instances of "'.GoogleChatOptions::class.'" for options.');

        $client = new MockHttpClient(fn (string $method, string $url, array $options = []): ResponseInterface => $this->createMock(ResponseInterface::class));

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }

    public function testSendWith200ResponseButNotOk()
    {
        $message = 'testMessage';

        $this->expectException(TransportException::class);

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

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }
}
