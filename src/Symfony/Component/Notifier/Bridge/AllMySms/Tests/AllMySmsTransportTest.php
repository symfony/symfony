<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AllMySmsTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = null): AllMySmsTransport
    {
        return new AllMySmsTransport('login', 'apiKey', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['allmysms://api.allmysms.com', self::createTransport()];
        yield ['allmysms://api.allmysms.com?from=TEST', self::createTransport(null, 'TEST')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
