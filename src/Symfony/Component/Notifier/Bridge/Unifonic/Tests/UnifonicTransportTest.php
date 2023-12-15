<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Unifonic\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Unifonic\UnifonicTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class UnifonicTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $host = null): UnifonicTransport
    {
        return (new UnifonicTransport('S3cr3t', 'Sender', $client ?? new MockHttpClient()))->setHost($host);
    }

    public static function toStringProvider(): iterable
    {
        yield ['unifonic://el.cloud.unifonic.com?from=Sender', self::createTransport()];
        yield ['unifonic://api.unifonic.com?from=Sender', self::createTransport(host: 'api.unifonic.com')];
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

    public function testSendFailedByStatusCode()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(info: [
            'http_code' => 400,
        ]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send SMS');

        $transport->send(new SmsMessage('0611223344', 'Hello!'));
    }

    public function testSendFailed()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse([
            'success' => false,
            'errorCode' => 'ER-123',
            'message' => 'Lorem Ipsum',
        ]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS. Reason: "Lorem Ipsum". Error code: "ER-123".');

        $transport->send(new SmsMessage('0611223344', 'Hello!'));
    }

    public function testSendSuccess()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse([
            'success' => true,
        ]));

        $transport = self::createTransport($client, host: 'localhost');
        $sentMessage = $transport->send(new SmsMessage('0611223344', 'Hello!'));

        $this->assertSame('unifonic://localhost?from=Sender', $sentMessage->getTransport());
        $this->assertSame('Hello!', $sentMessage->getOriginalMessage()->getSubject());
    }
}
