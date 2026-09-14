<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

class ChainStampTest extends TestCase
{
    public function testMessagesAreKeptInOrder()
    {
        $first = new DummyMessage('first');
        $second = new SecondMessage();

        $stamp = new ChainStamp($first, $second);

        $this->assertSame([$first, $second], $stamp->getMessages());
    }

    public function testEnvelopesAreAccepted()
    {
        $envelope = new Envelope(new DummyMessage('first'), [$delay = new DelayStamp(1000)]);

        $stamp = new ChainStamp($envelope, new SecondMessage());

        $this->assertSame($envelope->getMessage(), $stamp->getMessages()[0]->getMessage());
        $this->assertSame([$delay], $stamp->getMessages()[0]->all(DelayStamp::class));
    }

    public function testTheStampsAnEnvelopeCannotCarryToATransportAreDropped()
    {
        $envelope = new Envelope(new SecondMessage(), [$delay = new DelayStamp(1000), new ReceivedStamp('async')]);

        $stamp = new ChainStamp($envelope);

        $this->assertSame([], $stamp->getMessages()[0]->all(ReceivedStamp::class));
        $this->assertSame([$delay], $stamp->getMessages()[0]->all(DelayStamp::class));
    }

    public function testEmptyChainIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A chain needs at least one message.');

        new ChainStamp();
    }

    public function testSerializable()
    {
        $stamp = new ChainStamp(new DummyMessage('first'), new Envelope(new SecondMessage(), [new DelayStamp(1000)]));

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
