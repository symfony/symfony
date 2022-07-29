<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\Exception\LogicMessengerException;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\Messenger\ScheduleTransport;
use Symfony\Component\Scheduler\Schedule\ScheduleInterface;

class ScheduleTransportTest extends TestCase
{
    public function testGetFromIterator()
    {
        $messages = [
            (object) ['id' => 'first'],
            (object) ['id' => 'second'],
        ];
        $scheduler = $this->createConfiguredMock(ScheduleInterface::class, [
            'getMessages' => $messages,
        ]);
        $transport = new ScheduleTransport($scheduler);

        foreach ($transport->get() as $envelope) {
            $this->assertInstanceOf(Envelope::class, $envelope);
            $this->assertNotNull($envelope->last(ScheduledStamp::class));
            $this->assertSame(array_shift($messages), $envelope->getMessage());
        }

        $this->assertEmpty($messages);
    }

    public function testAckIgnored()
    {
        $transport = new ScheduleTransport($this->createMock(ScheduleInterface::class));

        $transport->ack(new Envelope(new \stdClass()));

        $this->assertTrue(true); // count coverage
    }

    public function testRejectException()
    {
        $transport = new ScheduleTransport($this->createMock(ScheduleInterface::class));

        $this->expectException(LogicMessengerException::class);
        $transport->reject(new Envelope(new \stdClass()));
    }

    public function testSendException()
    {
        $transport = new ScheduleTransport($this->createMock(ScheduleInterface::class));

        $this->expectException(LogicMessengerException::class);
        $transport->send(new Envelope(new \stdClass()));
    }
}
