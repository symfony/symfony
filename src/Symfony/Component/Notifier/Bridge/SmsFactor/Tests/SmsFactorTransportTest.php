<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsFactor\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\SmsFactor\SmsFactorPushType;
use Symfony\Component\Notifier\Bridge\SmsFactor\SmsFactorTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmsFactorTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): SmsFactorTransport
    {
        return (new SmsFactorTransport('TOKEN', 'MY_COMPANY', SmsFactorPushType::Alert, $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['sms-factor://host.test?sender=MY_COMPANY&push_type=alert', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+33611223344', 'Hello World!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello World!')];
        yield [new DummyMessage()];
    }
}
