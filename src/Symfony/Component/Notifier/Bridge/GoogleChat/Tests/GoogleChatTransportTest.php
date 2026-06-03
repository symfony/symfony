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
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatOptions;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
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
    public static function createTransport(?HttpClientInterface $client = null, ?string $threadKey = null): GoogleChatTransport
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

        $client = new MockHttpClient(new MockResponse('[]', ['http_code' => 500]));

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('API key not valid. Please pass a valid API key.');

        $client = new MockHttpClient(new MockResponse('{"error":{"code":400,"message":"API key not valid. Please pass a valid API key.","status":"INVALID_ARGUMENT"}}', ['http_code' => 400]));

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithOptions()
    {
        $message = 'testMessage';

        $expectedBody = json_encode(['text' => $message, 'thread' => ['threadKey' => 'My-Thread']]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://chat.googleapis.com/v1/spaces/My-Space/messages?key=theAccessKey&token=theAccessToken%3D&messageReplyOption=REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD', $url);
            $this->assertSame($expectedBody, $options['body']);

            return new MockResponse('{"name":"spaces/My-Space/messages/abcdefg.hijklmno"}');
        });

        $transport = self::createTransport($client, 'My-Thread');

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $notification = new Notification('testMessage');
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'text' => ' *testMessage* ',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new MockResponse('{"name":"spaces/My-Space/messages/abcdefg.hijklmno","thread":{"name":"spaces/My-Space/threads/abcdefg.hijklmno"}}');
        });

        $transport = self::createTransport($client);

        $sentMessage = $transport->send($chatMessage);

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }

    public function testSendWithInvalidOptions()
    {
        $options = $this->createStub(MessageOptionsInterface::class);
        $this->expectException(UnsupportedOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" transport only supports instances of "%s" for options (instance of "%s" given).', GoogleChatTransport::class, GoogleChatOptions::class, get_debug_type($options)));

        $client = new MockHttpClient(new MockResponse());

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage', $options));
    }

    public function testSendWith200ResponseButNotOk()
    {
        $message = 'testMessage';

        $this->expectException(TransportException::class);

        $expectedBody = json_encode(['text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new MockResponse('testErrorCode');
        });

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('spaces/My-Space/messages/abcdefg.hijklmno', $sentMessage->getMessageId());
    }
}
