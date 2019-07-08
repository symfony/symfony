<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class EnvelopeTest extends TestCase
{
    public function testConstruct()
    {
        $receivedStamp = new ReceivedStamp('transport');
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), [$receivedStamp]);

        $this->assertSame($dummy, $envelope->getMessage());
        $this->assertArrayHasKey(ReceivedStamp::class, $stamps = $envelope->all());
        $this->assertSame($receivedStamp, $stamps[ReceivedStamp::class][0]);
    }

    public function testWithReturnsNewInstance()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertNotSame($envelope, $envelope->with(new ReceivedStamp('transport')));
    }

    public function testWithoutAll()
    {
        $envelope = new Envelope(new DummyMessage('dummy'), [new ReceivedStamp('transport1'), new ReceivedStamp('transport2'), new DelayStamp(5000)]);

        $envelope = $envelope->withoutAll(ReceivedStamp::class);

        $this->assertEmpty($envelope->all(ReceivedStamp::class));
        $this->assertCount(1, $envelope->all(DelayStamp::class));
    }

    public function testWithoutStampsOfType()
    {
        $envelope = new Envelope(new DummyMessage('dummy'), [
            new ReceivedStamp('transport1'),
            new DummyExtendsFooBarStamp(),
            new DummyImplementsFooBarStamp(),
        ]);

        $envelope2 = $envelope->withoutStampsOfType(DummyNothingImplementsMeStampInterface::class);
        $this->assertEquals($envelope, $envelope2);

        $envelope3 = $envelope2->withoutStampsOfType(ReceivedStamp::class);
        $this->assertEmpty($envelope3->all(ReceivedStamp::class));

        $envelope4 = $envelope3->withoutStampsOfType(DummyImplementsFooBarStamp::class);
        $this->assertEmpty($envelope4->all(DummyImplementsFooBarStamp::class));
        $this->assertEmpty($envelope4->all(DummyExtendsFooBarStamp::class));

        $envelope5 = $envelope3->withoutStampsOfType(DummyFooBarStampInterface::class);
        $this->assertEmpty($envelope5->all());
    }

    public function testLast()
    {
        $receivedStamp = new ReceivedStamp('transport');
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), [$receivedStamp]);

        $this->assertSame($receivedStamp, $envelope->last(ReceivedStamp::class));
        $this->assertNull($envelope->last(ValidationStamp::class));
    }

    public function testAll()
    {
        $envelope = (new Envelope($dummy = new DummyMessage('dummy')))
            ->with($receivedStamp = new ReceivedStamp('transport'))
            ->with($validationStamp = new ValidationStamp(['foo']))
        ;

        $stamps = $envelope->all();
        $this->assertArrayHasKey(ReceivedStamp::class, $stamps);
        $this->assertSame($receivedStamp, $stamps[ReceivedStamp::class][0]);
        $this->assertArrayHasKey(ValidationStamp::class, $stamps);
        $this->assertSame($validationStamp, $stamps[ValidationStamp::class][0]);
    }

    public function testWrapWithMessage()
    {
        $message = new \stdClass();
        $stamp = new ReceivedStamp('transport');
        $envelope = Envelope::wrap($message, [$stamp]);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertSame([ReceivedStamp::class => [$stamp]], $envelope->all());
    }

    public function testWrapWithEnvelope()
    {
        $envelope = new Envelope(new \stdClass(), [new DelayStamp(5)]);
        $envelope = Envelope::wrap($envelope, [new ReceivedStamp('transport')]);

        $this->assertCount(1, $envelope->all(DelayStamp::class));
        $this->assertCount(1, $envelope->all(ReceivedStamp::class));
    }
}

interface DummyFooBarStampInterface extends StampInterface
{
}
interface DummyNothingImplementsMeStampInterface extends StampInterface
{
}
class DummyImplementsFooBarStamp implements DummyFooBarStampInterface
{
}
class DummyExtendsFooBarStamp extends DummyImplementsFooBarStamp
{
}
