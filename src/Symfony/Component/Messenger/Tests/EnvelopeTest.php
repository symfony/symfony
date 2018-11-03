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
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class EnvelopeTest extends TestCase
{
    public function testConstruct()
    {
        $receivedStamp = new ReceivedStamp();
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), $receivedStamp);

        $this->assertSame($dummy, $envelope->getMessage());
        $this->assertArrayHasKey(ReceivedStamp::class, $stamps = $envelope->all());
        $this->assertSame($receivedStamp, $stamps[ReceivedStamp::class]);
    }

    public function testWithReturnsNewInstance()
    {
        $envelope = new Envelope($dummy = new DummyMessage('dummy'));

        $this->assertNotSame($envelope, $envelope->with(new ReceivedStamp()));
    }

    public function testGet()
    {
        $receivedStamp = new ReceivedStamp();
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), $receivedStamp);

        $this->assertSame($receivedStamp, $envelope->get(ReceivedStamp::class));
        $this->assertNull($envelope->get(ValidationStamp::class));
    }

    public function testAll()
    {
        $envelope = (new Envelope($dummy = new DummyMessage('dummy')))
            ->with($receivedStamp = new ReceivedStamp())
            ->with($validationStamp = new ValidationStamp(array('foo')))
        ;

        $stamps = $envelope->all();
        $this->assertArrayHasKey(ReceivedStamp::class, $stamps);
        $this->assertSame($receivedStamp, $stamps[ReceivedStamp::class]);
        $this->assertArrayHasKey(ValidationStamp::class, $stamps);
        $this->assertSame($validationStamp, $stamps[ValidationStamp::class]);
    }
}
