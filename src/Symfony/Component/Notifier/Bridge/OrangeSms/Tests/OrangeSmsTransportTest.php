<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Esendex\OrangeSmsTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OrangeSmsTransportTest extends TransportTestCase
{
    /**
     * @return OrangeSmsTransport
     */
    public function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return (new OrangeSmsTransport('CLIENT_ID', 'CLIENT_SECRET', 'from', 'senderNname', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('default');
    }

    public function toStringProvider(): iterable
    {
        yield ['orangesms://default?from=FROM&sender_name=SENDER_NAME', $this->createTransport()];
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
