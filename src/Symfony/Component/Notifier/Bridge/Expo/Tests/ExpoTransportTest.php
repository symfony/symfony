<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Expo\ExpoTransport;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 */
final class ExpoTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): ExpoTransport
    {
        return new ExpoTransport('token', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['expo://exp.host/--/api/v2/push/send', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'Symfony Notifier')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0670802161', 'Hello!')];
        yield [new DummyMessage()];
    }
}
