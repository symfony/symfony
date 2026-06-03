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
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Discord\DiscordBotTransport;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DiscordBotTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): DiscordBotTransport
    {
        return (new DiscordBotTransport('testToken', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['discord+bot://host.test', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!', new DiscordOptions(['recipient_id' => 'channel_id']))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendThrowsWithoutRecipientId()
    {
        $transport = self::createTransport();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing configured recipient id on Discord message.');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = new JsonMockResponse(
            ['message' => 'testDescription', 'code' => 'testErrorCode'],
            ['http_code' => 400],
        );

        $client = new MockHttpClient($response);

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $transport->send(new ChatMessage('testMessage', (new DiscordOptions())->recipient('channel_id')));
    }
}
