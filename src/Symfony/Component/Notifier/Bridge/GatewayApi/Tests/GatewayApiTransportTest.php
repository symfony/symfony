<?php

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransport;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GatewayApiTransportTest extends TestCase
{
    public function testSend(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->getTransport($client);
        $message = new SmsMessage("3333333333", "Test Messgage");
        $sentMessage = $transport->send($message);
        $this->assertNotNull($sentMessage);
    }

    private function getTransport(MockHttpClient $client): GatewayApiTransport
    {
        return (new GatewayApiTransport(
            'authtoken',
            'Symfony',
            $client
        ))->setHost('host.test');
    }
}
