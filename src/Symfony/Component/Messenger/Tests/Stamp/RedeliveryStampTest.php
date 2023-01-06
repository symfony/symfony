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
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class RedeliveryStampTest extends TestCase
{
    public function testGetters()
    {
        $stamp = new RedeliveryStamp(10);
        $this->assertSame(10, $stamp->getRetryCount());
        $this->assertInstanceOf(\DateTimeInterface::class, $stamp->getRedeliveredAt());
    }

    public function testSerialization()
    {
        $stamp = new RedeliveryStamp(10, \DateTimeImmutable::createFromFormat(\DateTimeInterface::ISO8601, '2005-08-15T15:52:01+0000'));
        $this->assertSame('2005-08-15T15:52:01+0000', $stamp->getRedeliveredAt()->format(\DateTimeInterface::ISO8601));
    }

    public function testRedeliveryAt()
    {
        $redeliveredAt = new \DateTimeImmutable('+2minutes');
        $stamp = new RedeliveryStamp(10, $redeliveredAt);
        $this->assertSame($redeliveredAt, $stamp->getRedeliveredAt());
    }
}
