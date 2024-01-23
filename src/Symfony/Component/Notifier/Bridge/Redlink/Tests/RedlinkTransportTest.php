<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Redlink\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Redlink\RedlinkOptions;
use Symfony\Component\Notifier\Bridge\Redlink\RedlinkTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RedlinkTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): RedlinkTransport
    {
        return (new RedlinkTransport(
            'testApiToken',
            'testAppToken',
            'TEST',
            'v2.1',
            $client ?? new MockHttpClient()
        ))->setHost('api.redlink.pl');
    }

    public static function toStringProvider(): iterable
    {
        yield ['redlink://api.redlink.pl?from=TEST&version=v2.1', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+48123123123', 'Summary')];
        yield [new SmsMessage('+48123123123', 'Summary', '')];
        yield [new SmsMessage('+48123123123', 'Summary', 'customSender')];
        yield [new SmsMessage('+48123123123', 'Summary', '', (new RedlinkOptions())->externalId('aaa-aaa-aaa'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hi!', 'Hello!')];
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
