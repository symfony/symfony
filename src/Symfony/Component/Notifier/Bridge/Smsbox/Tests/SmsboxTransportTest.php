<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxOptions;
use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmsboxTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): SmsboxTransport
    {
        return new SmsboxTransport('apikey', 'Standard', 4, null, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsbox://api.smsbox.pro?mode=Standard&strategy=4', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+33612345678', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testBasicQuerySucceded()
    {
        $message = new SmsMessage('+33612345678', 'Hello!');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('OK 12345678');

        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsbox.pro/1.1/api.php', $url);
            self::assertSame('dest=%2B33612345678&msg=Hello%21&id=1&usage=symfony&mode=Standard&strategy=4', $request['body']);

            return $response;
        });

        $transport = $this->createTransport($client);
        $sentMessage = $transport->send($message);

        self::assertSame('12345678', $sentMessage->getMessageId());
    }

    public function testBasicQueryFailed()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: "ERROR 02" (400).');

        $message = new SmsMessage('+33612345678', 'Hello!');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('ERROR 02');

        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsbox.pro/1.1/api.php', $url);
            self::assertSame('dest=%2B33612345678&msg=Hello%21&id=1&usage=symfony&mode=Standard&strategy=4', $request['body']);

            return $response;
        });

        $transport = $this->createTransport($client);
        $transport->send($message);
    }

    public function testQuerySuccededWithOptions()
    {
        $message = new SmsMessage('+33612345678', 'Hello!');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('OK 12345678');

        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsbox.pro/1.1/api.php', $url);
            self::assertSame('max_parts=5&coding=unicode&callback=1&dest=%2B33612345678&msg=Hello%21&id=1&usage=symfony&mode=Standard&strategy=4&day_min=1&day_max=3', $request['body']);

            return $response;
        });

        $transport = $this->createTransport($client);
        $options = (new SmsboxOptions())
            ->maxParts(5)
            ->coding(SmsboxOptions::MESSAGE_CODING_UNICODE)
            ->daysMinMax(1, 3)
            ->callback(true);

        $message->options($options);
        $sentMessage = $transport->send($message);

        self::assertSame('12345678', $sentMessage->getMessageId());
    }

    public function testQueryDateTime()
    {
        $message = new SmsMessage('+33612345678', 'Hello!');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('OK 12345678');

        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsbox.pro/1.1/api.php', $url);
            self::assertSame('dest=%2B33612345678&msg=Hello%21&id=1&usage=symfony&mode=Standard&strategy=4&date=05%2F12%2F2025&heure=19%3A00', $request['body']);

            return $response;
        });

        $dateTime = \DateTime::createFromFormat('d/m/Y H:i', '05/12/2025 18:00', new \DateTimeZone('UTC'));

        $transport = $this->createTransport($client);

        $options = (new SmsboxOptions())
            ->dateTime($dateTime);

        $message->options($options);
        $sentMessage = $transport->send($message);

        self::assertSame('12345678', $sentMessage->getMessageId());
    }

    public function testQueryVariable()
    {
        $message = new SmsMessage('0612345678', 'Hello %1% %2%');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects(self::once())
            ->method('getContent')
            ->willReturn('OK 12345678');

        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsbox.pro/1.1/api.php', $url);
            self::assertSame('dest=0612345678%3Btye%25d44%25%25d44%25t%3Be%25d59%25%25d44%25fe&msg=Hello+%251%25+%252%25&id=1&usage=symfony&mode=Standard&strategy=4&personnalise=1', $request['body']);

            return $response;
        });

        $transport = $this->createTransport($client);

        $options = (new SmsboxOptions())
            ->variable(['tye,,t', 'e;,fe']);

        $message->options($options);
        $sentMessage = $transport->send($message);

        self::assertSame('12345678', $sentMessage->getMessageId());
    }

    public function testSmsboxOptionsInvalidDateTimeAndDate()
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            return $response;
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("You mustn't set the dateTime method along with date or hour methods");
        $dateTime = \DateTime::createFromFormat('d/m/Y H:i', '01/11/2024 18:00', new \DateTimeZone('UTC'));
        $message = new SmsMessage('+33612345678', 'Hello');

        $smsboxOptions = (new SmsboxOptions())
            ->mode(SmsboxOptions::MESSAGE_MODE_EXPERT)
            ->sender('SENDER')
            ->strategy(SmsboxOptions::MESSAGE_STRATEGY_MARKETING)
            ->dateTime($dateTime)
            ->date('01/01/2024');

        $transport = $this->createTransport($client);

        $message->options($smsboxOptions);
        $transport->send($message);
    }

    public function testSmsboxInvalidPhoneNumber()
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = new MockHttpClient(function (string $method, string $url, $request) use ($response): ResponseInterface {
            return $response;
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number');
        $message = new SmsMessage('+336123456789000000', 'Hello');

        $smsboxOptions = (new SmsboxOptions())
            ->mode(SmsboxOptions::MESSAGE_MODE_EXPERT)
            ->sender('SENDER')
            ->strategy(SmsboxOptions::MESSAGE_STRATEGY_MARKETING);
        $transport = $this->createTransport($client);

        $message->options($smsboxOptions);
        $transport->send($message);
    }
}
