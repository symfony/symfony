<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsSluzba\Tests;

use Symfony\Component\Notifier\Bridge\SmsSluzba\SmsSluzbaTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmsSluzbaTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?string $from = null): SmsSluzbaTransport
    {
        return new SmsSluzbaTransport('username', 'password');
    }

    public static function toStringProvider(): iterable
    {
        yield ['sms-sluzba://smsgateapi.sms-sluzba.cz', self::createTransport()];
        yield ['sms-sluzba://smsgateapi.sms-sluzba.cz', self::createTransport(null, 'TEST')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('608123456', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
