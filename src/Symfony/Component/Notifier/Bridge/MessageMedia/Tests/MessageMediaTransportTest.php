<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MessageMediaTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = null): MessageMediaTransport
    {
        return new MessageMediaTransport('apiKey', 'apiSecret', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['messagemedia://api.messagemedia.com', self::createTransport()];
        yield ['messagemedia://api.messagemedia.com?from=TEST', self::createTransport(null, 'TEST')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0491570156', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    /**
     * @dataProvider exceptionIsThrownWhenHttpSendFailedProvider
     *
     * @throws TransportExceptionInterface
     */
    public function testExceptionIsThrownWhenHttpSendFailed(int $statusCode, string $content, string $expectedExceptionMessage)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn($statusCode);
        $response->method('getContent')
            ->willReturn($content);

        $client = new MockHttpClient($response);

        $transport = new MessageMediaTransport('apiKey', 'apiSecret', null, $client);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $transport->send(new SmsMessage('+61491570156', 'Hello!'));
    }

    public static function exceptionIsThrownWhenHttpSendFailedProvider(): iterable
    {
        yield [503, '', 'Unable to send the SMS: "Unknown reason".'];
        yield [500, '{"details": ["Something went wrong."]}', 'Unable to send the SMS: "Something went wrong.".'];
        yield [403, '{"message": "Forbidden."}', 'Unable to send the SMS: "Forbidden.'];
        yield [401, '{"Unauthenticated"}', 'Unable to send the SMS: "Unknown reason".'];
    }
}
