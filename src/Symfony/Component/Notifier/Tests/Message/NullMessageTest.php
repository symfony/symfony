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
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\NullMessage;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class NullMessageTest extends TestCase
{
    /**
     * @dataProvider messageDataProvider
     */
    public function testCanBeConstructed(MessageInterface $message)
    {
        $nullMessage = new NullMessage($message);

        $this->assertSame($message->getSubject(), $nullMessage->getSubject());
        $this->assertSame($message->getRecipientId(), $nullMessage->getRecipientId());
        $this->assertSame($message->getOptions(), $nullMessage->getOptions());

        (null === $message->getTransport())
            ? $this->assertSame('null', $nullMessage->getTransport())
            : $this->assertSame($message->getTransport(), $nullMessage->getTransport());
    }

    public static function messageDataProvider(): \Generator
    {
        yield [new DummyMessageWithoutTransport()];
        yield [new DummyMessageWithTransport()];
    }
}
