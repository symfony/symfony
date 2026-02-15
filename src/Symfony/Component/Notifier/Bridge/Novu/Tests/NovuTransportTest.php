<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Novu\NovuOptions;
use Symfony\Component\Notifier\Bridge\Novu\NovuTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NovuTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return (new NovuTransport('9c9ced75881ddc65c033273f466b42d1', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['novu://host.test', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('test', '{}', new NovuOptions(123, null, null, 'test@example.com', null, null, null, ['email' => ['from' => 'no-reply@example.com', 'senderName' => 'No-Reply']], []))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testWithErrorResponseThrows()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['error' => 'Bad request', 'message' => 'subscriberId under property to is not configured']), ['http_code' => 400]));

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/400: "subscriberId under property to is not configured"/');

        $transport->send(new PushMessage('test', '{}', new NovuOptions(123, null, null, 'test@example.com', null, null, null, ['email' => ['from' => 'no-reply@example.com', 'senderName' => 'No-Reply']], [])));
    }
}
