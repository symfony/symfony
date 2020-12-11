<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('rocketchat://host.test?channel=testChannel', (string) $transport);
    }

    public function testSupportsChatMessage()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows()
    {
        $transport = $this->createTransport();

        $this->expectException(LogicException::class);
        $transport->send($this->createMock(MessageInterface::class));
    }

    private function createTransport(): RocketChatTransport
    {
        return (new RocketChatTransport('testAccessToken', 'testChannel', $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
