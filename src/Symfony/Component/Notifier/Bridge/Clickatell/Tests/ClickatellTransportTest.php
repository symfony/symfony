<?php

namespace Symfony\Component\Notifier\Bridge\Clickatell\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ClickatellTransportTest extends TestCase
{
    public function testToString()
    {
        $transport = new ClickatellTransport('authToken', 'fromValue', $this->createMock(HttpClientInterface::class));
        $transport->setHost('clickatellHost');

        $this->assertSame('clickatell://clickatellHost?from=fromValue', (string) $transport);
    }

    public function testSupports()
    {
        $transport = new ClickatellTransport('authToken', 'fromValue', $this->createMock(HttpClientInterface::class));

        $this->assertTrue($transport->supports(new SmsMessage('+33612345678', 'testSmsMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testExceptionIsThrownWhenNonMessageIsSend()
    {
        $transport = new ClickatellTransport('authToken', 'fromValue', $this->createMock(HttpClientInterface::class));

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

        $transport = new ClickatellTransport('authToken', 'fromValue', $client);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send SMS with Clickatell: Error code 105 with message "Invalid Account Reference EX0000000" (https://documentation-page).');

        $transport->send(new SmsMessage('+33612345678', 'testSmsMessage'));
    }
}
