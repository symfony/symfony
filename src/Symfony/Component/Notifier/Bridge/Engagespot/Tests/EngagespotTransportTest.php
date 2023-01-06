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

use Symfony\Component\Notifier\Bridge\Engagespot\EngagespotTransport;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null): EngagespotTransport
    {
        return new EngagespotTransport('apiKey', 'TEST', $client ?? $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['engagespot://api.engagespot.co/2/campaigns?campaign_name=TEST', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'Symfony Notifier')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0123456789', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
