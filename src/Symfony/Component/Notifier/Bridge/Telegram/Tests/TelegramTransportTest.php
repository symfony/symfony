<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramTransportTest extends TestCase
{
    public function testToStringContainsProperties(): void
    {
        $channel = 'testChannel';

        $transport = new TelegramTransport('testToken', $channel, $this->createMock(HttpClientInterface::class));
        $transport->setHost('testHost');

        $this->assertSame(sprintf('telegram://%s?channel=%s', 'testHost', $channel), (string) $transport);
    }

    public function testSupportsChatMessage(): void
    {
        $transport = new TelegramTransport('testToken', 'testChannel', $this->createMock(HttpClientInterface::class));

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows(): void
    {
        $this->expectException(LogicException::class);
        $transport = new TelegramTransport('testToken', 'testChannel', $this->createMock(HttpClientInterface::class));

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithErrorResponseThrows(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['description' => 'testDescription', 'error_code' => 'testErrorCode']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = new TelegramTransport('testToken', 'testChannel', $client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions(): void
    {
        $channel = 'testChannel';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('');

        $expectedBody = [
            'chat_id' => $channel,
            'text' => 'testMessage',
            'parse_mode' => 'Markdown',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertEquals($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = new TelegramTransport('testToken', $channel, $client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithChannelOverride(): void
    {
        $channelOverride = 'channelOverride';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('');

        $expectedBody = [
            'chat_id' => $channelOverride,
            'text' => 'testMessage',
            'parse_mode' => 'Markdown',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertEquals($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = new TelegramTransport('testToken', 'defaultChannel', $client);

        $messageOptions = $this->createMock(MessageOptionsInterface::class);
        $messageOptions
            ->expects($this->once())
            ->method('getRecipientId')
            ->willReturn($channelOverride);

        $transport->send(new ChatMessage('testMessage', $messageOptions));
    }
}
