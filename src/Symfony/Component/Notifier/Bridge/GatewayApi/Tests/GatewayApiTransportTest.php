<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class GatewayApiTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): GatewayApiTransport
    {
        return new GatewayApiTransport('authtoken', 'Symfony', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['gatewayapi://gatewayapi.com?from=Symfony', self::createTransport()];
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

    public function testSend()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['ids' => [42]]));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $message = new SmsMessage('3333333333', 'Hello!');

        $transport = self::createTransport($client);
        $sentMessage = $transport->send($message);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertSame('42', $sentMessage->getMessageId());
    }
}
