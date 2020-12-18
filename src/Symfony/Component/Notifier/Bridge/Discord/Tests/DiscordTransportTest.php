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
use Symfony\Component\Notifier\Exception\LengthException;
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
        $transport = $this->createTransport();

        $this->assertSame('discord://host.test?webhook_id=testWebhookId', (string) $transport);
    }

    public function testSupportsChatMessage()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrowsLogicException()
    {
        $transport = $this->createTransport();

        $this->expectException(UnsupportedMessageTypeException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendChatMessageWithMoreThan2000CharsThrowsLogicException()
    {
        $transport = $this->createTransport();

        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The subject length of a Discord message must not exceed 2000 characters.');

        $transport->send(new ChatMessage(str_repeat('囍', 2001)));
    }

    public function testSendWithErrorResponseThrows()
    {
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

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $transport->send(new ChatMessage('testMessage'));
    }

    private function createTransport(?HttpClientInterface $client = null): DiscordTransport
    {
        return (new DiscordTransport('testToken', 'testWebhookId', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
