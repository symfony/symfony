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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YunpianTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): YunpianTransport
    {
        return new YunpianTransport('api_key', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['yunpian://sms.yunpian.com', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSmsMessageWithFrom()
    {
        $transport = $this->createTransport();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransport" transport does not support "from" in "Symfony\Component\Notifier\Message\SmsMessage".');

        $transport->send(new SmsMessage('0600000000', 'test', 'foo'));
    }
}
