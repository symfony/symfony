<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sms77\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Sms77\Sms77Transport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Sms77TransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $from = null): Sms77Transport
    {
        return new Sms77Transport('apiKey', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['sms77://gateway.sms77.io', self::createTransport()];
        yield ['sms77://gateway.sms77.io?from=TEST', self::createTransport(null, 'TEST')];
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
