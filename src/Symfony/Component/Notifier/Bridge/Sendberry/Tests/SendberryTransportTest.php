<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendberry\Tests;

use Symfony\Component\Notifier\Bridge\Sendberry\SendberryTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\DummyHttpClient;
use Symfony\Component\Notifier\Tests\Fixtures\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendberryTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): SendberryTransport
    {
        return new SendberryTransport('username', 'password', 'auth_key', 'from', $client ?? new DummyHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['sendberry://api.sendberry.com?from=from', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
