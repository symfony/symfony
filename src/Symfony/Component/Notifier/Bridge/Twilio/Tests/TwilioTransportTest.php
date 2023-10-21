<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TwilioTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = 'from'): TwilioTransport
    {
        return new TwilioTransport('accountSid', 'authToken', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['twilio://api.twilio.com?from=from', self::createTransport()];
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

    /**
     * @dataProvider invalidFromProvider
     */
    public function testInvalidArgumentExceptionIsThrownIfFromIsInvalid(string $from)
    {
        $transport = self::createTransport(null, $from);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $from));

        $transport->send(new SmsMessage('+33612345678', 'Hello!'));
    }

    /**
     * @dataProvider invalidFromProvider
     */
    public function testInvalidArgumentExceptionIsThrownIfSmsMessageFromIsInvalid(string $from)
    {
        $transport = $this->createTransport();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $from));

        $transport->send(new SmsMessage('+33612345678', 'Hello!', $from));
    }

    public static function invalidFromProvider(): iterable
    {
        // alphanumeric sender ids
        yield 'too short' => ['a'];
        yield 'too long' => ['abcdefghijkl'];

        // phone numbers
        yield 'no zero at start if phone number' => ['+0'];
        yield 'phone number to short' => ['+1'];
    }

    /**
     * @dataProvider validFromProvider
     */
    public function testNoInvalidArgumentExceptionIsThrownIfFromIsValid(string $from)
    {
        $message = new SmsMessage('+33612345678', 'Hello!');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'sid' => '123',
                'message' => 'foo',
                'more_info' => 'bar',
            ]));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.twilio.com/2010-04-01/Accounts/accountSid/Messages.json', $url);

            return $response;
        });

        $transport = self::createTransport($client, $from);

        $sentMessage = $transport->send($message);

        $this->assertSame('123', $sentMessage->getMessageId());
    }

    public static function validFromProvider(): iterable
    {
        // alphanumeric sender ids
        yield ['ab'];
        yield ['abc'];
        yield ['abcd'];
        yield ['abcde'];
        yield ['abcdef'];
        yield ['abcdefg'];
        yield ['abcdefgh'];
        yield ['abcdefghi'];
        yield ['abcdefghij'];
        yield ['abcdefghijk'];
        yield ['abcdef ghij'];
        yield [' abcdefghij'];
        yield ['abcdefghij '];

        // phone numbers
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
