<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;

class SingleMessageReceiverTest extends TestCase
{
    public function testItReceivesOnlyOneMessage()
    {
        $innerReceiver = $this->createMock(ReceiverInterface::class);
        $envelope = new Envelope(new \stdClass());

        $receiver = new SingleMessageReceiver($innerReceiver, $envelope);
        $received = $receiver->get();
        $this->assertCount(1, $received);
        $this->assertSame($received[0], $envelope);

        $this->assertEmpty($receiver->get());
    }

    public function testCallsAreForwarded()
    {
        $envelope = new Envelope(new \stdClass());

        $innerReceiver = $this->createMock(ReceiverInterface::class);
        $innerReceiver->expects($this->once())->method('ack')->with($envelope);
        $innerReceiver->expects($this->once())->method('reject')->with($envelope);

        $receiver = new SingleMessageReceiver($innerReceiver, $envelope);
        $receiver->ack($envelope);
        $receiver->reject($envelope);
    }
}
