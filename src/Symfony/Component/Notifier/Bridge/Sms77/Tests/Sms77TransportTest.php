<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sms77\Tests;

use Symfony\Component\Notifier\Bridge\Sms77\Sms77Transport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Sms77TransportTest extends TransportTestCase
{
    /**
     * @return Sms77Transport
     */
    public function createTransport(HttpClientInterface $client = null, string $from = null): TransportInterface
    {
        return new Sms77Transport('apiKey', $from, $client ?? self::createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['sms77://gateway.sms77.io', $this->createTransport()];
        yield ['sms77://gateway.sms77.io?from=TEST', $this->createTransport(null, 'TEST')];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }
}
