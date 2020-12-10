<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ZulipTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('zulip://test.host?channel=testChannel', (string) $transport);
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

    private function createTransport(): ZulipTransport
    {
        return (new ZulipTransport('testEmail', 'testToken', 'testChannel', $this->createMock(HttpClientInterface::class)))->setHost('test.host');
    }
}
