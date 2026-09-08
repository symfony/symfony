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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class FailedMessageStampTest extends TestCase
{
    public function testMessageIsKept()
    {
        $message = new DummyMessage('failed');

        $stamp = new FailedMessageStamp($message);

        $this->assertSame($message, $stamp->getMessage());
    }

    public function testEnvelopeIsAccepted()
    {
        $envelope = new Envelope(new DummyMessage('failed'), [new DelayStamp(1000)]);

        $stamp = new FailedMessageStamp($envelope);

        $this->assertSame($envelope, $stamp->getMessage());
    }

    public function testSerializable()
    {
        $stamp = new FailedMessageStamp(new Envelope(new DummyMessage('failed'), [new DelayStamp(1000)]));

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
