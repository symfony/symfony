<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Clickatell\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ClickatellTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, string $from = null): ClickatellTransport
    {
        return new ClickatellTransport('authToken', $from, $client ?? $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['clickatell://api.clickatell.com', $this->createTransport()];
        yield ['clickatell://api.clickatell.com?from=TEST', $this->createTransport(null, 'TEST')];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+33612345678', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testExceptionIsThrownWhenNonMessageIsSend()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testExceptionIsThrownWhenHttpSendFailed()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'error' => [
                    'code' => 105,
                    'description' => 'Invalid Account Reference EX0000000',
                    'documentation' => 'https://documentation-page',
                ],
            ]));

        $client = new MockHttpClient($response);

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send SMS with Clickatell: Error code 105 with message "Invalid Account Reference EX0000000" (https://documentation-page).');

        $transport->send(new SmsMessage('+33612345678', 'Hello!'));
    }
}
