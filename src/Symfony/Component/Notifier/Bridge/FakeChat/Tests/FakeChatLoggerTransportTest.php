<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatLoggerTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\TestOptions;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FakeChatLoggerTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, LoggerInterface $logger = null): TransportInterface
    {
        return new FakeChatLoggerTransport($logger ?? self::createMock(LoggerInterface::class), $client ?? self::createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['fakechat+logger://default', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }

    public function testSendWithDefaultTransport()
    {
        $message1 = new ChatMessage($subject1 = 'Hello subject1!', new TestOptions(['recipient_id' => $recipient1 = 'Oskar']));
        $message2 = new ChatMessage($subject2 = 'Hello subject2!');

        $logger = new TestLogger();

        $transport = $this->createTransport(null, $logger);

        $transport->send($message1);
        $transport->send($message2);

        $logs = $logger->logs;
        self::assertNotEmpty($logs);

        $log1 = $logs[0];
        self::assertSame(sprintf('New Chat message for recipient: %s: %s', $recipient1, $subject1), $log1['message']);
        self::assertSame('info', $log1['level']);

        $log2 = $logs[1];
        self::assertSame(sprintf('New Chat message without specified recipient!: %s', $subject2), $log2['message']);
    }
}
