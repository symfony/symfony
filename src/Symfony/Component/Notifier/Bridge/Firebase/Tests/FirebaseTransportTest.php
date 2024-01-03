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
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): FirebaseTransport
    {
        return new FirebaseTransport('username:password', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['firebase://fcm.googleapis.com/fcm/send', self::createTransport()];
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

    /**
     * @dataProvider sendWithErrorThrowsExceptionProvider
     */
    public function testSendWithErrorThrowsTransportException(ResponseInterface $response)
    {
        $this->expectException(TransportException::class);

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);
        $options = new class('recipient-id', []) extends FirebaseOptions {};

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('Hello!', $options));
    }

    public static function sendWithErrorThrowsExceptionProvider(): iterable
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
