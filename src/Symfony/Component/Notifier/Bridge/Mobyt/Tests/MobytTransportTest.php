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

use Symfony\Component\Notifier\Bridge\Mobyt\MobytOptions;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MobytTransportTest extends TransportTestCase
{
    /**
     * @return MobytTransport
     */
    public function createTransport(?HttpClientInterface $client = null, string $messageType = MobytOptions::MESSAGE_TYPE_QUALITY_LOW): TransportInterface
    {
        return (new MobytTransport('accountSid', 'authToken', 'from', $messageType, $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['mobyt://host.test?from=from&type_quality=LL', $this->createTransport()];
        yield ['mobyt://host.test?from=from&type_quality=N', $this->createTransport(null, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH)];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
