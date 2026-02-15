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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaOptions;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MessageMediaTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?string $from = null): MessageMediaTransport
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
        yield [new SmsMessage('0491570156', 'Hello!', 'from', new MessageMediaOptions(['from' => 'foo']))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[DataProvider('exceptionIsThrownWhenHttpSendFailedProvider')]
    public function testExceptionIsThrownWhenHttpSendFailed(int $statusCode, string $content, string $expectedExceptionMessage)
    {
        $client = new MockHttpClient(new MockResponse($content, ['http_code' => $statusCode]));

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
