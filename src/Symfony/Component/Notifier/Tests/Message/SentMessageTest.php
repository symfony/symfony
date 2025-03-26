<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;

class SentMessageTest extends TestCase
{
    public function test()
    {
        $originalMessage = new DummyMessage();

        $sentMessage = new SentMessage($originalMessage, 'any', ['foo' => 'bar']);
        $sentMessage->setMessageId('the_id');

        $this->assertSame($originalMessage, $sentMessage->getOriginalMessage());
        $this->assertSame('any', $sentMessage->getTransport());
        $this->assertSame('the_id', $sentMessage->getMessageId());
        $this->assertSame(['foo' => 'bar'], $sentMessage->getInfo());
        $this->assertSame('bar', $sentMessage->getInfo('foo'));
        $this->assertNull($sentMessage->getInfo('not_existing'));
    }
}
