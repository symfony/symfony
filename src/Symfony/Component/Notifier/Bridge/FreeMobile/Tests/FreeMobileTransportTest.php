<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FreeMobileTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport('0611223344');

        $this->assertSame('freemobile://host.test?phone=0611223344', (string) $transport);
    }

    /**
     * @dataProvider supportsProvider
     */
    public function testSupportsMessageInterface(bool $expected, string $configuredPhoneNumber, MessageInterface $message)
    {
        $transport = $this->createTransport($configuredPhoneNumber);

        $this->assertSame($expected, $transport->supports($message));
    }

    /**
     * @return iterable<array{0: bool, 1: string, 2: MessageInterface}>
     */
    public function supportsProvider(): iterable
    {
        yield [true, '0611223344', new SmsMessage('0611223344', 'Hello!')];
        yield [true, '0611223344', new SmsMessage('+33611223344', 'Hello!')];
        yield [false, '0611223344', new SmsMessage('0699887766', 'Hello!')];
        yield [false, '0611223344', $this->createMock(MessageInterface::class)];
        yield [true, '+33611223344', new SmsMessage('0611223344', 'Hello!')];
        yield [true, '+33611223344', new SmsMessage('+33611223344', 'Hello!')];
    }

    public function testSendNonSmsMessageThrowsLogicException()
    {
        $transport = $this->createTransport('0611223344');

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendSmsMessageButInvalidPhoneThrowsLogicException()
    {
        $transport = $this->createTransport('0611223344');

        $this->expectException(LogicException::class);

        $transport->send(new SmsMessage('0699887766', 'Hello!'));
    }

    private function createTransport(string $phone): FreeMobileTransport
    {
        return (new FreeMobileTransport('login', 'pass', $phone, $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
