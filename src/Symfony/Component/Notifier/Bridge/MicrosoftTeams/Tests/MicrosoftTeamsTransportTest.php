<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsOptions;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MicrosoftTeamsTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): MicrosoftTeamsTransport
    {
        return (new MicrosoftTeamsTransport('/testPath', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['microsoftteams://host.test/testPath', self::createTransport()];
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
        $client = new MockHttpClient(fn (string $method, string $url, array $options = []): ResponseInterface => new MockResponse('testErrorMessage', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 400]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testErrorMessage/');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorRequestIdThrows()
    {
        $client = new MockHttpClient(new MockResponse());

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/request-id not found/');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSend()
    {
        $message = 'testMessage';

        $expectedBody = json_encode([
            'text' => $message,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('1', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 200]);
        });

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage($message));
    }

    public function testSendWithOptionsAndTextOverwritesChatMessage()
    {
        $message = 'testMessage';
        $options = new MicrosoftTeamsOptions([
            'text' => $optionsTextMessage = 'optionsTestMessage',
        ]);

        $expectedBody = json_encode([
            'text' => $optionsTextMessage,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('1', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 200]);
        });

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage($message, $options));
    }

    public function testSendWithOptionsAsMessageCard()
    {
        $title = 'title';
        $message = 'testMessage';

        $options = new MicrosoftTeamsOptions([
            'title' => $title,
        ]);

        $expectedBody = json_encode([
            '@context' => 'https://schema.org/extensions',
            '@type' => 'MessageCard',
            'text' => $message,
            'title' => $title,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('1', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 200]);
        });

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage($message, $options));
    }

    public function testSendFromNotification()
    {
        $notification = new Notification($message = 'testMessage');
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'text' => $message,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('1', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 200]);
        });

        $transport = self::createTransport($client);

        $transport->send($chatMessage);
    }
}
