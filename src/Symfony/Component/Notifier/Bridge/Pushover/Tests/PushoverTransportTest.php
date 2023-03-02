<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushover\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Pushover\PushoverTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PushoverTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): PushoverTransport
    {
        return new PushoverTransport('userKey', 'appToken', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['pushover://api.pushover.net', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'World')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithOptions()
    {
        $messageSubject = 'testMessageSubject';
        $messageContent = 'testMessageContent';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['status' => 1, 'request' => 'uuid']));

        $expectedBody = http_build_query([
            'message' => 'testMessageContent',
            'title' => 'testMessageSubject',
            'token' => 'appToken',
            'user' => 'userKey',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });
        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new PushMessage($messageSubject, $messageContent));

        $this->assertSame('uuid', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $messageSubject = 'testMessageSubject';
        $messageContent = 'testMessageContent';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['status' => 1, 'request' => 'uuid']));

        $notification = (new Notification($messageSubject))->content($messageContent);
        $pushMessage = PushMessage::fromNotification($notification);

        $expectedBody = http_build_query([
            'message' => 'testMessageContent',
            'title' => 'testMessageSubject',
            'token' => 'appToken',
            'user' => 'userKey',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });
        $transport = self::createTransport($client);

        $sentMessage = $transport->send($pushMessage);

        $this->assertSame('uuid', $sentMessage->getMessageId());
    }
}
