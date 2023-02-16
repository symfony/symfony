<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineNotify\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\LineNotify\LineNotifyTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Akira Kurozumi <info@a-zumi.net>
 */
final class LineNotifyTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): LineNotifyTransport
    {
        return (new LineNotifyTransport('testToken', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['linenotify://host.test', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['message' => 'testDescription', 'code' => 'testErrorCode', 'status' => 'testStatus']));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testMessage.+testDescription/');

        $transport->send(new ChatMessage('testMessage'));
    }
}
