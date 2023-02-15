<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher\Tests;

use Pusher\Pusher;
use Symfony\Component\Notifier\Bridge\Pusher\PusherTransport;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 *
 * @coversNothing
 */
final class PusherTransportTest extends TransportTestCase
{
    public function toStringProvider(): iterable
    {
        yield ['pusher://key:secret@app?server=mt1', $this->createTransport()];
    }

    /**
     * @return PusherTransport
     */
    public static function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return new PusherTransport(new Pusher('key', 'secret', 'app'), $client);
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('event', 'data')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testCanSetCustomHost()
    {
        static::markTestSkipped('Does not apply for this provider.');
    }

    public function testCanSetCustomPort()
    {
        static::markTestSkipped('Does not apply for this provider.');
    }

    public function testCanSetCustomHostAndPort()
    {
        static::markTestSkipped('Does not apply for this provider.');
    }
}
