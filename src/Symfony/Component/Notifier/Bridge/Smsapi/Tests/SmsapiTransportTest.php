<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmsapiTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('smsapi://test.host?from=testFrom', (string) $transport);
    }

    public function testSupportsMessageInterface()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);
        $transport->send($this->createMock(MessageInterface::class));
    }

    private function createTransport(): SmsapiTransport
    {
        return (new SmsapiTransport('testToken', 'testFrom', $this->createMock(HttpClientInterface::class)))->setHost('test.host');
    }
}
