<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytOptions;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MobytTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $messageType = MobytOptions::MESSAGE_TYPE_QUALITY_LOW): MobytTransport
    {
        return (new MobytTransport('accountSid', 'authToken', 'from', $messageType, $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['mobyt://host.test?from=from&type_quality=LL', self::createTransport()];
        yield ['mobyt://host.test?from=from&type_quality=N', self::createTransport(null, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH)];
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
