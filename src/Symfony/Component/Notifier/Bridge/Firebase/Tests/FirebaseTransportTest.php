<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('firebase://host.test', (string) $transport);
    }

    public function testSupportsMessageInterface()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonSmsMessageThrowsLogicException()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    private function createTransport(): FirebaseTransport
    {
        return (new FirebaseTransport('username:password', $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
