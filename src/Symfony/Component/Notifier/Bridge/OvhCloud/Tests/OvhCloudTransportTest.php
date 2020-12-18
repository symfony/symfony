<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OvhCloudTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('ovhcloud://host.test?consumer_key=consumerKey&service_name=serviceName', (string) $transport);
    }

    public function testSupportsMessageInterface()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonSmsMessageThrowsLogicException()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    private function createTransport(): OvhCloudTransport
    {
        return (new OvhCloudTransport('applicationKey', 'applicationSecret', 'consumerKey', 'serviceName', $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
