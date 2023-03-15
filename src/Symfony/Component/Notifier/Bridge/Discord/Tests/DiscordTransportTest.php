<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransport;
use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DiscordTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): DiscordTransport
    {
        return (new DiscordTransport('testToken', 'testWebhookId', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['discord://host.test?webhook_id=testWebhookId', self::createTransport()];
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

    public function testSendChatMessageWithMoreThan2000CharsThrowsLogicException()
    {
        $transport = self::createTransport();

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length of a Discord message must not exceed 2000 characters.');

        $transport->send(new ChatMessage(str_repeat('囍', 2001)));
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['message' => 'testDescription', 'code' => 'testErrorCode']));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $transport->send(new ChatMessage('testMessage'));
    }
}
