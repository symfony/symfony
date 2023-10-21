<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $channel = null): RocketChatTransport
    {
        return new RocketChatTransport('testAccessToken', $channel, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['rocketchat://rocketchat.com', self::createTransport()];
        yield ['rocketchat://rocketchat.com?channel=testChannel', self::createTransport(null, 'testChannel')];
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
