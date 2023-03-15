<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Bandwidth\BandwidthTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BandwidthTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = 'from'): BandwidthTransport
    {
        return new BandwidthTransport('username', 'password', $from, 'account_id', 'application_id', 'priority', $client ?? new MockHttpClient());
    }

    public function invalidFromProvider(): iterable
    {
        yield 'no zero at start if phone number' => ['+0'];
        yield 'phone number too short' => ['+1'];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    /**
     * @dataProvider invalidFromProvider
     */
    public function testInvalidArgumentExceptionIsThrownIfFromIsInvalid(string $from)
    {
        $transport = $this->createTransport(null, $from);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "From" number "%s" is not a valid phone number. The number must be in E.164 format.', $from));

        $transport->send(new SmsMessage('+33612345678', 'Hello!'));
    }

    /**
     * @dataProvider validFromProvider
     */
    public function testNoInvalidArgumentExceptionIsThrownIfFromIsValid(string $from)
    {
        $message = new SmsMessage('+33612345678', 'Hello!');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))->method('getStatusCode')->willReturn(202);
        $response->expects(self::once())->method('getContent')->willReturn(json_encode(['id' => 'foo']));
        $client = new MockHttpClient(function (string $method, string $url) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://messaging.bandwidth.com/api/v2/users/account_id/messages', $url);

            return $response;
        });
        $transport = $this->createTransport($client, $from);
        $sentMessage = $transport->send($message);
        self::assertSame('foo', $sentMessage->getMessageId());
    }

    public static function toStringProvider(): iterable
    {
        yield ['bandwidth://messaging.bandwidth.com?from=from&account_id=account_id&application_id=application_id&priority=priority', self::createTransport()];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public static function validFromProvider(): iterable
    {
        yield ['+11'];
        yield ['+112'];
        yield ['+1123'];
        yield ['+11234'];
        yield ['+112345'];
        yield ['+1123456'];
        yield ['+11234567'];
        yield ['+112345678'];
        yield ['+1123456789'];
        yield ['+11234567891'];
        yield ['+112345678912'];
        yield ['+1123456789123'];
        yield ['+11234567891234'];
        yield ['+112345678912345'];
    }
}
