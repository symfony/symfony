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
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;

class CorrelationStampTest extends TestCase
{
    public function testTheGivenIdIsKept()
    {
        $this->assertSame('the-request-id', (new CorrelationStamp('the-request-id'))->getId());
    }

    public function testItIsPropagated()
    {
        $this->assertInstanceOf(PropagatedStampInterface::class, new CorrelationStamp('the-request-id'));
    }

    public function testItIsSerializable()
    {
        $stamp = new CorrelationStamp('the-request-id');

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
