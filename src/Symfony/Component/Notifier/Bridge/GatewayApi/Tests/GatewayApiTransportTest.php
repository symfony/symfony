<?php

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GatewayApiTransportTest extends TestCase
{
    public function testSend()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->getTransport($client);
        $message = new SmsMessage('3333333333', 'Test Messgage');
        $sentMessage = $transport->send($message);
        $this->assertNotNull($sentMessage);
    }

    public function testSupportsSmsMessage()
    {
        $transport = $this->getTransport();
        $message = new SmsMessage('3333333333', 'Test Messgage');
        $this->assertTrue($transport->supports($message));
    }

    public function testNotSupportsChatMessage()
    {
        $transport = $this->getTransport();
        $message = new ChatMessage('3333333333');
        $this->assertFalse($transport->supports($message));
    }

    private function getTransport(MockHttpClient $client = null): GatewayApiTransport
    {
        return (new GatewayApiTransport('authtoken', 'Symfony', $client))->setHost('host.test');
    }
}
