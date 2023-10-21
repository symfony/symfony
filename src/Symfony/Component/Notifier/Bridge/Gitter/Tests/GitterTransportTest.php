<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Gitter\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
final class GitterTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): GitterTransport
    {
        return (new GitterTransport('token', '5539a3ee5etest0d3255bfef', $client ?? new MockHttpClient()))->setHost('api.gitter.im');
    }

    public static function toStringProvider(): iterable
    {
        yield ['gitter://api.gitter.im?room_id=5539a3ee5etest0d3255bfef', self::createTransport()];
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
}
