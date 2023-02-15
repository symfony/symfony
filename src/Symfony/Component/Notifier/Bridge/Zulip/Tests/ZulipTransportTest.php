<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip\Tests;

use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\DummyHttpClient;
use Symfony\Component\Notifier\Tests\Fixtures\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ZulipTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): ZulipTransport
    {
        return (new ZulipTransport('testEmail', 'testToken', 'testChannel', $client ?? new DummyHttpClient()))->setHost('test.host');
    }

    public static function toStringProvider(): iterable
    {
        yield ['zulip://test.host?channel=testChannel', self::createTransport()];
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
