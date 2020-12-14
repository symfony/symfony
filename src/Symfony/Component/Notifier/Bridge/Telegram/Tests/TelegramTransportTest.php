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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Tests\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramTransportTest extends TransportTestCase
{
    /**
     * @return TelegramTransport
     */
    public function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return new TelegramTransport('token', 'testChannel', $client ?: $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['telegram://api.telegram.org?channel=testChannel', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
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
            ->willReturn(json_encode(['description' => 'testDescription', 'error_code' => 'testErrorCode']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('');

        $expectedBody = [
            'chat_id' => 'testChannel',
            'text' => 'testMessage',
            'parse_mode' => 'Markdown',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithChannelOverride()
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
            $this->assertSame($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = new TelegramTransport('token', $channelOverride, $client);

        $messageOptions = $this->createMock(MessageOptionsInterface::class);
        $messageOptions
            ->expects($this->once())
            ->method('getRecipientId')
            ->willReturn($channelOverride);

        $transport->send(new ChatMessage('testMessage', $messageOptions));
    }
}
