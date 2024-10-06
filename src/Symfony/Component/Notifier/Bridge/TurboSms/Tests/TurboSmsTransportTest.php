<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\TurboSms\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\TurboSms\TurboSmsTransport;
use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TurboSmsTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): TurboSmsTransport
    {
        return new TurboSmsTransport('authToken', 'sender', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['turbosms://api.turbosms.ua?from=sender', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('380931234567', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSuccessfulSend()
    {
        $response = new JsonMockResponse(body: [
            'response_code' => 0,
            'response_status' => 'OK',
            'response_result' => [
                [
                    'phone' => '380931234567',
                    'response_code' => 0,
                    'message_id' => 'f83f8868-5e46-c6cf-e4fb-615e5a293754',
                    'response_status' => 'OK',
                ],
            ],
        ], info: ['http_code' => 200]);

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($response): ResponseInterface {
            $body = json_decode($options['body'], true);
            self::assertSame([
                'sms' => [
                    'sender' => 'sender',
                    'recipients' => ['380931234567'],
                    'text' => 'Тест/Test',
                ],
            ], $body);

            return $response;
        });

        $message = new SmsMessage('380931234567', 'Тест/Test');

        $transport = self::createTransport($client);
        $sentMessage = $transport->send($message);

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('f83f8868-5e46-c6cf-e4fb-615e5a293754', $sentMessage->getMessageId());
    }

    public function testFailedSendWithPartialAccepted()
    {
        $response = new JsonMockResponse(body: [
            'response_code' => 0,
            'response_status' => 'OK',
            'response_result' => [
                [
                    'phone' => '380931234567',
                    'response_code' => 406,
                    'message_id' => null,
                    'response_status' => 'NOT_ALLOWED_RECIPIENT_COUNTRY',
                ],
            ],
        ], info: ['http_code' => 200]);

        $client = new MockHttpClient(static fn () => $response);

        $message = new SmsMessage('380931234567', 'Test');

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send SMS with TurboSMS: Error code 406 with message "NOT_ALLOWED_RECIPIENT_COUNTRY".');

        $transport->send($message);
    }

    public function testFailedSend()
    {
        $response = new JsonMockResponse(body: [
            'response_code' => 103,
            'response_status' => 'REQUIRED_TOKEN',
            'response_result' => null,
        ], info: ['http_code' => 400]);

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $message = new SmsMessage('380931234567', 'Тест/Test');

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send SMS with TurboSMS: Error code 103 with message "REQUIRED_TOKEN".');

        $transport->send($message);
    }

    public function testInvalidFrom()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The sender length of a TurboSMS message must not exceed 20 characters.');

        new TurboSmsTransport('authToken', 'abcdefghijklmnopqrstu', $this->createMock(HttpClientInterface::class));
    }

    public function testInvalidSubjectWithLatinSymbols()
    {
        $message = new SmsMessage('380931234567', str_repeat('z', 1522));
        $transport = new TurboSmsTransport('authToken', 'sender', $this->createMock(HttpClientInterface::class));

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length for "latin" symbols of a TurboSMS message must not exceed 1521 characters.');

        $transport->send($message);
    }

    public function testInvalidSubjectWithCyrillicSymbols()
    {
        $message = new SmsMessage('380931234567', str_repeat('z', 661).'Й');
        $transport = new TurboSmsTransport('authToken', 'sender', $this->createMock(HttpClientInterface::class));

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length for "cyrillic" symbols of a TurboSMS message must not exceed 661 characters.');

        $transport->send($message);
    }

    public function testSmsMessageWithInvalidFrom()
    {
        $transport = $this->createTransport();

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The sender length of a TurboSMS message must not exceed 20 characters.');

        $transport->send(new SmsMessage('380931234567', 'test', 'abcdefghijklmnopqrstu'));
    }
}
