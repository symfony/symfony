<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Matrix\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Matrix\MatrixOptions;
use Symfony\Component\Notifier\Bridge\Matrix\MatrixTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MatrixTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?bool $ssl = false): MatrixTransport
    {
        return new MatrixTransport('apiKey', $ssl, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['matrix://', self::createTransport(null, true)];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!', new MatrixOptions(['recipient_id' => '#testchannelalias:matrix.io', 'format' => 'org.matrix.custom.html']))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testUnsupportedRecipients()
    {
        $transport = self::createTransport();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only recipients starting with "!","@","#" are supported ("+" given).');
        $transport->send(new ChatMessage('Hello!', new MatrixOptions(['recipient_id' => '+testchannelalias:matrix.io'])));
    }

    public function testUnsupportedMsgType()
    {
        $transport = self::createTransport();
        $this->expectException(LogicException::class);
        $transport->send(new ChatMessage('Hello!', new MatrixOptions(['recipient_id' => '@user:matrix.io', 'msgtype' => 'm.anything'])));
    }
}
