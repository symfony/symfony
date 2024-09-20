<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushy\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Pushy\PushyOptions;
use Symfony\Component\Notifier\Bridge\Pushy\PushyTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PushyTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): PushyTransport
    {
        return new PushyTransport('apiKey', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['pushy://api.pushy.me', self::createTransport()];
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

        $expectedBody = json_encode([
            'to' => 'device',
            'data' => $messageContent,
            'notification' => ['title' => $messageSubject],
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new JsonMockResponse(['success' => true, 'id' => '123']);
        });
        $transport = self::createTransport($client);

        $options = new PushyOptions();
        $options->to('device');

        $sentMessage = $transport->send(new PushMessage($messageSubject, $messageContent, $options));

        $this->assertSame('123', $sentMessage->getMessageId());
    }

    public function testSendWithNotification()
    {
        $messageSubject = 'testMessageSubject';
        $messageContent = 'testMessageContent';

        $options = new PushyOptions();
        $options->to('device');

        $notification = (new Notification($messageSubject))->content($messageContent);
        $pushMessage = PushMessage::fromNotification($notification);
        $pushMessage->options($options);

        $expectedBody = json_encode([
            'to' => 'device',
            'data' => $messageContent,
            'notification' => ['title' => $messageSubject],
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new JsonMockResponse(['success' => true, 'id' => '123']);
        });
        $transport = self::createTransport($client);

        $sentMessage = $transport->send($pushMessage);

        $this->assertSame('123', $sentMessage->getMessageId());
    }
}
