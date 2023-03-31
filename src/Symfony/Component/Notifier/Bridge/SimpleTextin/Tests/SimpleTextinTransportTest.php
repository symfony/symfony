<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SimpleTextin\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\SimpleTextin\SimpleTextinOptions;
use Symfony\Component\Notifier\Bridge\SimpleTextin\SimpleTextinTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SimpleTextinTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = 'test_from'): SimpleTextinTransport
    {
        return new SimpleTextinTransport('test_api_key', $from, $client ?? new MockHttpClient());
    }

    public function invalidFromProvider(): iterable
    {
        yield 'no zero at start if phone number' => ['+0'];
        yield 'phone number too short' => ['+1'];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('0611223344', 'Hello!', 'from', new SimpleTextinOptions(['from' => 'from_new']))];
    }

    /**
     * @dataProvider invalidFromProvider
     */
    public function testInvalidArgumentExceptionIsThrownIfFromIsInvalid(string $from)
    {
        $transport = $this->createTransport(null, $from);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "From" number "%s" is not a valid phone number.', $from));

        $transport->send(new SmsMessage('+33612345678', 'Hello!'));
    }

    /**
     * @dataProvider validFromProvider
     */
    public function testNoInvalidArgumentExceptionIsThrownIfFromIsValid(string $from)
    {
        $message = new SmsMessage('+33612345678', 'Hello!');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))->method('getStatusCode')->willReturn(201);
        $response->expects(self::once())->method('getContent')->willReturn(json_encode(['id' => 'foo']));

        $client = new MockHttpClient(function (string $method, string $url) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api-app2.simpletexting.com/v2/api/messages', $url);

            return $response;
        });

        $transport = $this->createTransport($client, $from);

        $sentMessage = $transport->send($message);
        self::assertSame('foo', $sentMessage->getMessageId());
    }

    public static function toStringProvider(): iterable
    {
        yield ['simpletextin://api-app2.simpletexting.com?from=test_from', self::createTransport()];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function validFromProvider(): iterable
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
