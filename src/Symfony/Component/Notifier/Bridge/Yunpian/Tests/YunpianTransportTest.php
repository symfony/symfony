<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Yunpian\Tests;

use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YunpianTransportTest extends TransportTestCase
{
    /**
     * @return YunpianTransport
     */
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return new YunpianTransport('api_key', $client ?? self::createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['yunpian://sms.yunpian.com', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }
}
