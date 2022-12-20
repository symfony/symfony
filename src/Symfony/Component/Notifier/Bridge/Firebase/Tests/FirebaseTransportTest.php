<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportTest extends TransportTestCase
{
    /**
     * @return FirebaseTransport
     */
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return new FirebaseTransport('username:password', $client ?? self::createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['firebase://fcm.googleapis.com/fcm/send', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }

    /**
     * @dataProvider sendWithErrorThrowsExceptionProvider
     */
    public function testSendWithErrorThrowsTransportException(ResponseInterface $response)
    {
        self::expectException(TransportException::class);

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });
        $options = new class('recipient-id', []) extends FirebaseOptions {};

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage('Hello!', $options));
    }

    public function sendWithErrorThrowsExceptionProvider(): iterable
    {
        yield [new MockResponse(
            json_encode(['results' => [['error' => 'testErrorCode']]]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 200]
        )];

        yield [new MockResponse(
            json_encode(['results' => [['error' => 'testErrorCode']]]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 400]
        )];
    }
}
