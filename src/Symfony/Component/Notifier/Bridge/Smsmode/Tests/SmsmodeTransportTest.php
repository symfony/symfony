<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsmode\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Smsmode\SmsmodeOptions;
use Symfony\Component\Notifier\Bridge\Smsmode\SmsmodeTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmsmodeTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, string $from = 'test_from'): SmsmodeTransport
    {
        return new SmsmodeTransport('test_api_key', $from, $client ?? new MockHttpClient());
    }

    public static function invalidFromProvider(): iterable
    {
        yield 'sender number too send' => ['aaaaaaaaaaaa'];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('0611223344', 'Hello!', 'from', new SmsmodeOptions(['ref_client' => 'test_ref_client']))];
    }

    #[DataProvider('invalidFromProvider')]
    public function testInvalidArgumentExceptionIsThrownIfFromIsInvalid(string $from)
    {
        $transport = $this->createTransport(null, $from);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "From" value "%s" is not a valid sender ID.', $from));

        $transport->send(new SmsMessage('+33612345678', 'Hello!'));
    }

    #[DataProvider('validFromProvider')]
    public function testNoInvalidArgumentExceptionIsThrownIfFromIsValid(string $from)
    {
        $message = new SmsMessage('+33612345678', 'Hello!');

        $client = new MockHttpClient(static function (string $method, string $url): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://rest.smsmode.com/sms/v1/messages', $url);

            return new MockResponse(json_encode(['messageId' => 'foo']), ['http_code' => 201]);
        });

        $transport = $this->createTransport($client, $from);

        $sentMessage = $transport->send($message);

        self::assertSame('foo', $sentMessage->getMessageId());
    }

    public function testHttpClientHasMandatoryHeaderAccept()
    {
        $message = new SmsMessage('+33612345678', 'Hello!');

        $transport = $this->createTransport(new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://rest.smsmode.com/sms/v1/messages', $url);
            $this->assertSame('Accept: application/json', $options['normalized_headers']['accept'][0]);

            return new MockResponse(json_encode(['messageId' => 'foo']), ['http_code' => 201]);
        }), 'foo');

        $result = $transport->send($message);

        $this->assertSame('foo', $result->getMessageId());
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsmode://rest.smsmode.com?from=test_from', self::createTransport()];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public static function validFromProvider(): iterable
    {
        yield ['a'];
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
    }
}
