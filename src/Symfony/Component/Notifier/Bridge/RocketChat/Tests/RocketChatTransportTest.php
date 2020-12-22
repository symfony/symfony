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

use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Tests\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportTest extends TransportTestCase
{
    /**
     * @return RocketChatTransport
     */
    public function createTransport(?HttpClientInterface $client = null, string $channel = null): TransportInterface
    {
        return new RocketChatTransport('testAccessToken', $channel, $client ?: $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['rocketchat://rocketchat.com', $this->createTransport()];
        yield ['rocketchat://rocketchat.com?channel=testChannel', $this->createTransport(null, 'testChannel')];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
