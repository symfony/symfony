<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DiscordTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $webhookId = 'testChannel';

        $transport = new DiscordTransport('testToken', $webhookId, $this->createMock(HttpClientInterface::class));
        $transport->setHost('testHost');

        $this->assertSame(sprintf('discord://%s?webhook_id=%s', 'testHost', $webhookId), (string) $transport);
    }

    public function testSupportsChatMessage()
    {
        $transport = new DiscordTransport('testToken', 'testChannel', $this->createMock(HttpClientInterface::class));

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows()
    {
        $transport = new DiscordTransport('testToken', 'testChannel', $this->createMock(HttpClientInterface::class));

        $this->expectException(UnsupportedMessageTypeException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithErrorResponseThrows()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['message' => 'testDescription', 'code' => 'testErrorCode']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = new DiscordTransport('testToken', 'testChannel', $client);

        $transport->send(new ChatMessage('testMessage'));
    }
}
