<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sevenio\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Sevenio\SevenIoTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SevenIoTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?string $from = null): SevenIoTransport
    {
        return new SevenIoTransport('apiKey', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['sevenio://gateway.seven.io', self::createTransport()];
        yield ['sevenio://gateway.seven.io?from=TEST', self::createTransport(null, 'TEST')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithSuccessCodeReturnedAsString()
    {
        $response = new MockResponse(json_encode([
            'success' => '100',
            'messages' => [['id' => '1234567890']],
        ]));

        $transport = self::createTransport(new MockHttpClient($response));
        $sentMessage = $transport->send(new SmsMessage('0611223344', 'Hello!'));

        $this->assertSame('1234567890', $sentMessage->getMessageId());
    }

    public function testSendWithErrorCodeThrows()
    {
        $response = new MockResponse(json_encode(['success' => '900']));

        $transport = self::createTransport(new MockHttpClient($response));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: "900".');

        $transport->send(new SmsMessage('0611223344', 'Hello!'));
    }
}
