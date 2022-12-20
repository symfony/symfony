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
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineReceivedStamp as LegacyDoctrineReceivedStamp;
use Symfony\Component\Messenger\Transport\RedisExt\RedisReceivedStamp as LegacyRedisReceivedStamp;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class EnvelopeTest extends TestCase
{
    public function testConstruct()
    {
        $receivedStamp = new ReceivedStamp('transport');
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), [$receivedStamp]);

        self::assertSame($dummy, $envelope->getMessage());
        self::assertArrayHasKey(ReceivedStamp::class, $stamps = $envelope->all());
        self::assertSame($receivedStamp, $stamps[ReceivedStamp::class][0]);
    }

    public function testWithReturnsNewInstance()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        self::assertNotSame($envelope, $envelope->with(new ReceivedStamp('transport')));
    }

    public function testWithoutAll()
    {
        $envelope = new Envelope(new DummyMessage('dummy'), [new ReceivedStamp('transport1'), new ReceivedStamp('transport2'), new DelayStamp(5000)]);

        $envelope = $envelope->withoutAll(ReceivedStamp::class);

        self::assertEmpty($envelope->all(ReceivedStamp::class));
        self::assertCount(1, $envelope->all(DelayStamp::class));
    }

    public function testWithoutAllWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        self::assertInstanceOf(Envelope::class, $envelope->withoutAll(NonExistentStamp::class));
    }

    public function testWithoutStampsOfType()
    {
        $envelope = new Envelope(new DummyMessage('dummy'), [
            new ReceivedStamp('transport1'),
            new DummyExtendsFooBarStamp(),
            new DummyImplementsFooBarStamp(),
        ]);

        $envelope2 = $envelope->withoutStampsOfType(DummyNothingImplementsMeStampInterface::class);
        self::assertEquals($envelope, $envelope2);

        $envelope3 = $envelope2->withoutStampsOfType(ReceivedStamp::class);
        self::assertEmpty($envelope3->all(ReceivedStamp::class));

        $envelope4 = $envelope3->withoutStampsOfType(DummyImplementsFooBarStamp::class);
        self::assertEmpty($envelope4->all(DummyImplementsFooBarStamp::class));
        self::assertEmpty($envelope4->all(DummyExtendsFooBarStamp::class));

        $envelope5 = $envelope3->withoutStampsOfType(DummyFooBarStampInterface::class);
        self::assertEmpty($envelope5->all());
    }

    public function testWithoutStampsOfTypeWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        self::assertInstanceOf(Envelope::class, $envelope->withoutStampsOfType(NonExistentStamp::class));
    }

    public function testLast()
    {
        $receivedStamp = new ReceivedStamp('transport');
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), [$receivedStamp]);

        self::assertSame($receivedStamp, $envelope->last(ReceivedStamp::class));
        self::assertNull($envelope->last(ValidationStamp::class));
    }

    public function testLastWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        self::assertNull($envelope->last(NonExistentStamp::class));
    }

    public function testAll()
    {
        $envelope = (new Envelope($dummy = new DummyMessage('dummy')))
            ->with($receivedStamp = new ReceivedStamp('transport'))
            ->with($validationStamp = new ValidationStamp(['foo']))
        ;

        $stamps = $envelope->all();
        self::assertArrayHasKey(ReceivedStamp::class, $stamps);
        self::assertSame($receivedStamp, $stamps[ReceivedStamp::class][0]);
        self::assertArrayHasKey(ValidationStamp::class, $stamps);
        self::assertSame($validationStamp, $stamps[ValidationStamp::class][0]);
    }

    public function testAllWithNonExistentStampClass()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        self::assertSame([], $envelope->all(NonExistentStamp::class));
    }

    public function testWrapWithMessage()
    {
        $message = new \stdClass();
        $stamp = new ReceivedStamp('transport');
        $envelope = Envelope::wrap($message, [$stamp]);

        self::assertSame($message, $envelope->getMessage());
        self::assertSame([ReceivedStamp::class => [$stamp]], $envelope->all());
    }

    public function testWrapWithEnvelope()
    {
        $envelope = new Envelope(new \stdClass(), [new DelayStamp(5)]);
        $envelope = Envelope::wrap($envelope, [new ReceivedStamp('transport')]);

        self::assertCount(1, $envelope->all(DelayStamp::class));
        self::assertCount(1, $envelope->all(ReceivedStamp::class));
    }

    /**
     * To be removed in 6.0.
     *
     * @group legacy
     */
    public function testWithAliases()
    {
        $envelope = new Envelope(new \stdClass(), [
            $s1 = new DoctrineReceivedStamp(1),
            $s2 = new RedisReceivedStamp(2),
            $s3 = new DoctrineReceivedStamp(3),
        ]);

        self::assertSame([
            DoctrineReceivedStamp::class => [$s1, $s3],
            RedisReceivedStamp::class => [$s2],
        ], $envelope->all());

        self::assertSame([$s1, $s3], $envelope->all(DoctrineReceivedStamp::class));
        self::assertSame([$s2], $envelope->all(RedisReceivedStamp::class));

        self::assertSame([$s1, $s3], $envelope->all(LegacyDoctrineReceivedStamp::class));
        self::assertSame([$s2], $envelope->all(LegacyRedisReceivedStamp::class));

        self::assertSame($s3, $envelope->last(LegacyDoctrineReceivedStamp::class));
        self::assertSame($s2, $envelope->last(LegacyRedisReceivedStamp::class));

        self::assertSame([RedisReceivedStamp::class => [$s2]], $envelope->withoutAll(LegacyDoctrineReceivedStamp::class)->all());
        self::assertSame([DoctrineReceivedStamp::class => [$s1, $s3]], $envelope->withoutAll(LegacyRedisReceivedStamp::class)->all());

        self::assertSame([RedisReceivedStamp::class => [$s2]], $envelope->withoutStampsOfType(LegacyDoctrineReceivedStamp::class)->all());
        self::assertSame([DoctrineReceivedStamp::class => [$s1, $s3]], $envelope->withoutStampsOfType(LegacyRedisReceivedStamp::class)->all());
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
