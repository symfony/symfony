<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotOptions;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): LineBotTransport
    {
        return (new LineBotTransport('testToken', 'testReceiver', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['linebot://host.test?receiver=testReceiver', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new ChatMessage('Hello!', new LineBotOptions())];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithErrorResponseThrows()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['message' => 'testDescription']), ['http_code' => 400]));

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testMessage.+400: "testDescription"/');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendUsesTheReceiverFromTheDsn()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame([
                'to' => 'testReceiver',
                'messages' => [['type' => 'text', 'text' => 'testMessage']],
            ], json_decode($options['body'], true));

            return new JsonMockResponse([]);
        });

        $this->createTransport($client)->send(new ChatMessage('testMessage'));
    }

    public function testSendUsesTheRecipientFromTheOptions()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame([
                'to' => 'customReceiver',
                'messages' => [['type' => 'text', 'text' => 'testMessage']],
            ], json_decode($options['body'], true));

            return new JsonMockResponse([]);
        });

        $this->createTransport($client)->send(new ChatMessage('testMessage', (new LineBotOptions())->to('customReceiver')));
    }

    public function testSendWithForeignOptionsThrows()
    {
        $options = $this->createStub(MessageOptionsInterface::class);

        $this->expectException(UnsupportedOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" transport only supports instances of "%s" for options (instance of "%s" given).', LineBotTransport::class, LineBotOptions::class, get_debug_type($options)));

        $this->createTransport()->send(new ChatMessage('testMessage', $options));
    }
}
