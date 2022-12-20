<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sync;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

class SyncTransportTest extends TestCase
{
    public function testSend()
    {
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function ($arg) {
                self::assertInstanceOf(Envelope::class, $arg);

                return true;
            }))
            ->willReturnArgument(0);
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $transport = new SyncTransport($bus);
        $envelope = $transport->send($envelope);

        self::assertSame($message, $envelope->getMessage());
        self::assertNotNull($envelope->last(ReceivedStamp::class));
    }
}
