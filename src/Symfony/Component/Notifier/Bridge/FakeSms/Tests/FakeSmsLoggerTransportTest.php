<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsLoggerTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FakeSmsLoggerTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, LoggerInterface $logger = null): FakeSmsLoggerTransport
    {
        $transport = (new FakeSmsLoggerTransport($logger ?? $this->createMock(LoggerInterface::class), $client ?? $this->createMock(HttpClientInterface::class)));

        return $transport;
    }

    public function toStringProvider(): iterable
    {
        yield ['fakesms+logger://default', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('+33611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testSendWithDefaultTransport()
    {
        $message = new SmsMessage($phone = '0611223344', 'Hello!');

        $logger = new TestLogger();

        $transport = $this->createTransport(null, $logger);

        $transport->send($message);

        $logs = $logger->logs;
        $this->assertNotEmpty($logs);

        $log = $logs[0];
        $this->assertSame(sprintf('New SMS on phone number: %s', $phone), $log['message']);
        $this->assertSame('info', $log['level']);
    }
}
