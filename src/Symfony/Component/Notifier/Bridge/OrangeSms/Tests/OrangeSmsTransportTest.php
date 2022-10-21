<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OrangeSms\Tests;

use Symfony\Component\Notifier\Bridge\OrangeSms\OrangeSmsTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OrangeSmsTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null): OrangeSmsTransport
    {
        return (new OrangeSmsTransport('CLIENT_ID', 'CLIENT_SECRET', 'FROM', 'SENDER_NAME', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['orange-sms://host.test?from=FROM&sender_name=SENDER_NAME', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+243899999999', 'Hello World!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello World!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
