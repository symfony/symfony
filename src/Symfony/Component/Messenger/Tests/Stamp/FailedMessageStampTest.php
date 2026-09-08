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

    public function testOnlyTheMessageOfAnEnvelopeIsKept()
    {
        $message = new DummyMessage('failed');

        $stamp = new FailedMessageStamp(new Envelope($message, [new DelayStamp(1000)]));

        $this->assertSame($message, $stamp->getMessage());
    }

    public function testSerializable()
    {
        $stamp = new FailedMessageStamp(new DummyMessage('failed'));

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
