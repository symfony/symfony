<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotOptions;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): LineBotTransport
    {
        return (new LineBotTransport('testToken', 'testReceiver', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['linebot://host.test?receiver=testReceiver', self::createTransport()];
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

    public static function validMessageProvider(): iterable
    {
        return [
            'subject only message' => [
                LineBotOptions::fromNotification(
                    new Notification('testSubject')
                )->to('testReceiver'),
                new ChatMessage('testSubject'),
            ],
            'notification' => [
                LineBotOptions::fromNotification(
                    new Notification('testSubject')
                )->to('testReceiver'),
                ChatMessage::fromNotification(
                    new Notification('testSubject')
                ),
            ],
            'notification with options without message' => [
                (new LineBotOptions())
                    ->to('testReceiver')
                    ->addMessage([
                        'type' => 'text',
                        'text' => 'testSubject',
                    ])
                    ->disableNotification(false),
                ChatMessage::fromNotification(
                    new Notification('testSubject')
                )->options((new LineBotOptions())->disableNotification(false)),
            ],
            'notification with options with message' => [
                (new LineBotOptions())
                    ->to('testReceiver')
                    ->addMessage([
                        'type' => 'text',
                        'text' => 'Hello',
                    ])
                    ->disableNotification(false),
                ChatMessage::fromNotification(
                    new Notification('testSubject')
                )->options((new LineBotOptions())->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])->disableNotification(false)),
            ],
        ];
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['message' => 'testDescription']));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testMessage.+400: "testDescription"/');

        $transport->send(new ChatMessage('testMessage'));
    }

    /**
     * @dataProvider validMessageProvider
     */
    public function testValidMessage(LineBotOptions $lineBotOptions, ChatMessage $message)
    {
        $client = $this->createMock(HttpClientInterface::class);

        $client->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('The request should not be sent'))
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo([
                    'auth_bearer' => 'testToken',
                    'json' => $lineBotOptions->toArray(),
                ]),
            )
        ;

        $this->expectException(\Exception::class);

        $transport = $this->createTransport($client);
        $transport->send($message);
    }

    public function testSendNotificationWithInvalidOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid message provided.');

        $transport = $this->createTransport();
        $transport->send((new ChatMessage('testSubject'))->options(
            $this->createMock(MessageOptionsInterface::class)
        ));
    }
}
