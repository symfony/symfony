<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InfobipTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->getTransport();

        $this->assertSame('infobip://host.test?from=0611223344', (string) $transport);
    }

    public function testSupportsMessageInterface()
    {
        $transport = $this->getTransport();

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonSmsMessageThrowsException()
    {
        $transport = $this->getTransport();

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    private function getTransport(): InfobipTransport
    {
        return (new InfobipTransport(
            'authtoken',
            '0611223344',
            $this->createMock(HttpClientInterface::class)
        ))->setHost('host.test');
    }
}
