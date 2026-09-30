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

    public function testWithoutAllWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertInstanceOf(Envelope::class, $envelope->withoutAll(NonExistentStamp::class));
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

    public function testWithoutStampsOfTypeWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertInstanceOf(Envelope::class, $envelope->withoutStampsOfType(NonExistentStamp::class));
    }

    public function testLast()
    {
        $receivedStamp = new ReceivedStamp('transport');
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), [$receivedStamp]);

        $this->assertSame($receivedStamp, $envelope->last(ReceivedStamp::class));
        $this->assertNull($envelope->last(ValidationStamp::class));
    }

    public function testLastWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertNull($envelope->last(NonExistentStamp::class));
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

    public function testAllWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertSame([], $envelope->all(NonExistentStamp::class));
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

    public function testUnserializeKeepsWellFormedEnvelopes()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertEquals($envelope, unserialize(serialize($envelope)));

        $envelope = new Envelope(new DummyMessage('dummy'), [new ReceivedStamp('a'), new DelayStamp(5), new ReceivedStamp('b')]);
        $unserialized = unserialize(serialize($envelope));

        $this->assertEquals($envelope, $unserialized);
        $this->assertSame([ReceivedStamp::class, DelayStamp::class], array_keys($unserialized->all()));
    }

    public function testUnserializeKeepsStampsOfMissingClasses()
    {
        $serialized = serialize(new Envelope(new DummyMessage('dummy'), [new DelayStamp(5)]));
        // simulate a stamp class that was removed from the code base
        $serialized = str_replace('DelayStamp', 'DelayStomp', $serialized);

        $envelope = unserialize($serialized);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $envelope->all('Symfony\Component\Messenger\Stamp\DelayStomp')[0]);
    }

    public function testUnserializeRejectsStampsFiledUnderAnotherClass()
    {
        $delayed = serialize(new Envelope(new DummyMessage('dummy'), [new DelayStamp(5)]));
        $incomplete = serialize(new Envelope(new DummyMessage('dummy'), [unserialize('O:12:"MissingStamp":0:{}')]));

        foreach ([
            str_replace(serialize(DelayStamp::class), serialize(ReceivedStamp::class), $delayed),
            str_replace(serialize('__PHP_Incomplete_Class'), serialize(DelayStamp::class), $incomplete),
        ] as $serialized) {
            try {
                unserialize($serialized);
                $this->fail('Unserializing an envelope that files a stamp under another class should fail.');
            } catch (\BadMethodCallException $e) {
                $this->assertSame('Cannot unserialize '.Envelope::class, $e->getMessage());
            }
        }
    }

    public function testUnserializeRejectsObjectsThatAreNotStamps()
    {
        $serialized = serialize(new Envelope(new DummyMessage('dummy'), [new DummyMessage('not a stamp')]));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.Envelope::class);

        unserialize($serialized);
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
