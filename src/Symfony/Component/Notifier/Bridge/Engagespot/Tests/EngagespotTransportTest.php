<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Engagespot\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Engagespot\EngagespotTransport;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): EngagespotTransport
    {
        return new EngagespotTransport('apiKey', 'TEST', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['engagespot://api.engagespot.co/2/campaigns?campaign_name=TEST', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'Symfony Notifier')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0123456789', 'Hello!')];
        yield [new DummyMessage()];
    }
}
