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
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;

class MessageIdStampTest extends TestCase
{
    public function testItHoldsTheIdOfTheMessage()
    {
        $this->assertSame('the-message-id', (new MessageIdStamp('the-message-id'))->getId());
    }

    public function testItIsSentButNotPropagated()
    {
        $stamp = new MessageIdStamp('the-message-id');

        $this->assertNotInstanceOf(PropagatedStampInterface::class, $stamp);
        $this->assertNotInstanceOf(NonSendableStampInterface::class, $stamp);
    }

    public function testItIsSerializable()
    {
        $stamp = new MessageIdStamp('the-message-id');

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
