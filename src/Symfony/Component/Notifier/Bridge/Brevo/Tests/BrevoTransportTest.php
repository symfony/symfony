<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Brevo\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Brevo\BrevoTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BrevoTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): BrevoTransport
    {
        return (new BrevoTransport('api-key', '0611223344', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['brevo://host.test?sender=0611223344', self::createTransport()];
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

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse(json_encode(['code' => 400, 'message' => 'bad request']), ['http_code' => 400]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: bad request');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendUsesTheTransactionalSmsSendEndpoint()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://host.test/v3/transactionalSMS/send', $url);
            $this->assertSame(['sender' => '0611223344', 'recipient' => '0699887766', 'content' => 'Hello!'], json_decode($options['body'], true));

            return new MockResponse(json_encode(['messageId' => 1511882900176220]), ['http_code' => 201]);
        });

        $sentMessage = self::createTransport($client)->send(new SmsMessage('0699887766', 'Hello!'));

        $this->assertSame('1511882900176220', $sentMessage->getMessageId());
    }
}
