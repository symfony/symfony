<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OneSignal\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalOptions;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class OneSignalTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $recipientId = null): OneSignalTransport
    {
        return new OneSignalTransport('9fb175f0-0b32-4e99-ae97-bd228b9eb246', 'api_key', $recipientId, $client ?? new MockHttpClient());
    }

    public function testCanSetCustomHost()
    {
        $transport = self::createTransport();

        $transport->setHost($customHost = self::CUSTOM_HOST);

        $this->assertSame(sprintf('onesignal://9fb175f0-0b32-4e99-ae97-bd228b9eb246@%s', $customHost), (string) $transport);
    }

    public function testCanSetCustomHostAndPort()
    {
        $transport = self::createTransport();

        $transport->setHost($customHost = self::CUSTOM_HOST);
        $transport->setPort($customPort = self::CUSTOM_PORT);

        $this->assertSame(sprintf('onesignal://9fb175f0-0b32-4e99-ae97-bd228b9eb246@%s:%d', $customHost, $customPort), (string) $transport);
    }

    public static function toStringProvider(): iterable
    {
        yield ['onesignal://9fb175f0-0b32-4e99-ae97-bd228b9eb246@onesignal.com', self::createTransport()];
        yield ['onesignal://9fb175f0-0b32-4e99-ae97-bd228b9eb246@onesignal.com?recipientId=ea345989-d273-4f21-a33b-0c006efc5edb', self::createTransport(null, 'ea345989-d273-4f21-a33b-0c006efc5edb')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello', 'World'), self::createTransport(null, 'ea345989-d273-4f21-a33b-0c006efc5edb')];
        yield [new PushMessage('Hello', 'World', (new OneSignalOptions())->recipient('ea345989-d273-4f21-a33b-0c006efc5edb'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testUnsupportedWithoutRecipientId()
    {
        $this->assertFalse(self::createTransport()->supports(new PushMessage('Hello', 'World')));
    }

    public function testSendThrowsWithoutRecipient()
    {
        $transport = self::createTransport();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransport" transport should have configured `defaultRecipientId` via DSN or provided with message options.');

        $transport->send(new PushMessage('Hello', 'World'));
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['errors' => ['Message Notifications must have English language content']]));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client, 'ea345989-d273-4f21-a33b-0c006efc5edb');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/Message Notifications must have English language content/');

        $transport->send(new PushMessage('Hello', 'World'));
    }

    public function testSendWithErrorResponseThrowsWhenAllUnsubscribed()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '', 'recipients' => 0, 'errors' => ['All included players are not subscribed']]));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client, 'ea345989-d273-4f21-a33b-0c006efc5edb');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/All included players are not subscribed/');

        $transport->send(new PushMessage('Hello', 'World'));
    }

    public function testSend()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => 'b98881cc-1e94-4366-bbd9-db8f3429292b', 'recipients' => 1, 'external_id' => null]));

        $expectedBody = json_encode(['app_id' => '9fb175f0-0b32-4e99-ae97-bd228b9eb246', 'headings' => ['en' => 'Hello'], 'contents' => ['en' => 'World'], 'include_player_ids' => ['ea345989-d273-4f21-a33b-0c006efc5edb']]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return $response;
        });

        $transport = self::createTransport($client, 'ea345989-d273-4f21-a33b-0c006efc5edb');

        $sentMessage = $transport->send(new PushMessage('Hello', 'World'));

        $this->assertSame('b98881cc-1e94-4366-bbd9-db8f3429292b', $sentMessage->getMessageId());
    }
}
