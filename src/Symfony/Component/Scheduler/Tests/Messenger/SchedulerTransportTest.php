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
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGeneratorInterface;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class SchedulerTransportTest extends TestCase
{
    public function testGetFromIterator()
    {
        $messages = [
            (object) ['id' => 'first'],
            (object) ['id' => 'second'],
        ];
        $generator = $this->createMock(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function () use ($messages): \Generator {
            $trigger = $this->createMock(TriggerInterface::class);
            $triggerAt = new \DateTimeImmutable('2020-02-20T02:00:00', new \DateTimeZone('UTC'));
            yield (new MessageContext('default', 'id1', $trigger, $triggerAt)) => $messages[0];
            yield (new MessageContext('default', 'id2', $trigger, $triggerAt)) => $messages[1];
        });
        $transport = new SchedulerTransport($generator);

        foreach ($transport->get() as $i => $envelope) {
            $this->assertInstanceOf(Envelope::class, $envelope);
            $this->assertNotNull($stamp = $envelope->last(ScheduledStamp::class));
            $this->assertSame(array_shift($messages), $envelope->getMessage());
            $this->assertSame('default', $stamp->messageContext->name);
            $this->assertSame('id'.$i + 1, $stamp->messageContext->id);
        }

        $this->assertSame([], $messages);
    }

    public function testAddsStampToInnerRedispatchMessageEnvelope()
    {
        $generator = $this->createMock(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function (): \Generator {
            yield new MessageContext('default', 'id', $this->createMock(TriggerInterface::class), new \DateTimeImmutable()) => new RedispatchMessage(new \stdClass(), ['transport']);
        });
        $envelopes = iterator_to_array((new SchedulerTransport($generator))->get());

        $stamp = $envelopes[0]->getMessage()->envelope->last(ScheduledStamp::class);

        $this->assertSame($stamp, $envelopes[0]->last(ScheduledStamp::class));
        $this->assertSame('default', $stamp->messageContext->name);
        $this->assertSame('id', $stamp->messageContext->id);
    }

    public function testAckIgnored()
    {
        $transport = new SchedulerTransport($this->createMock(MessageGeneratorInterface::class));

        $this->expectNotToPerformAssertions();
        $transport->ack(new Envelope(new \stdClass()));
    }

    public function testRejectException()
    {
        $transport = new SchedulerTransport($this->createMock(MessageGeneratorInterface::class));

        $this->expectNotToPerformAssertions();
        $transport->reject(new Envelope(new \stdClass()));
    }

    public function testSendException()
    {
        $transport = new SchedulerTransport($this->createMock(MessageGeneratorInterface::class));

        $this->expectException(LogicException::class);
        $transport->send(new Envelope(new \stdClass()));
    }
}
