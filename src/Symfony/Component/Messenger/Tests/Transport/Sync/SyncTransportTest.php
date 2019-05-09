<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

class SyncTransportTest extends TestCase
{
    public function testSend()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(Envelope::class, $arg);

                return true;
            }))
            ->willReturnArgument(0);
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $transport = new SyncTransport($bus);
        $envelope = $transport->send($envelope);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertNotNull($envelope->last(ReceivedStamp::class));
    }
}
