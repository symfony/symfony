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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class RedeliveryStampTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testGetters()
    {
        $stamp = new RedeliveryStamp(10);
        self::assertSame(10, $stamp->getRetryCount());
        self::assertInstanceOf(\DateTimeInterface::class, $stamp->getRedeliveredAt());
    }

    public function testSerialization()
    {
        $stamp = new RedeliveryStamp(10, \DateTimeImmutable::createFromFormat(\DateTimeInterface::ISO8601, '2005-08-15T15:52:01+0000'));
        self::assertSame('2005-08-15T15:52:01+0000', $stamp->getRedeliveredAt()->format(\DateTimeInterface::ISO8601));
    }

    public function testRedeliveryAt()
    {
        $redeliveredAt = new \DateTimeImmutable('+2minutes');
        $stamp = new RedeliveryStamp(10, $redeliveredAt);
        self::assertSame($redeliveredAt, $stamp->getRedeliveredAt());
    }

    /**
     * @group legacy
     */
    public function testLegacyRedeliveryAt()
    {
        $this->expectDeprecation('Since symfony/messenger 5.2: Using the "$redeliveredAt" as 4th argument of the "Symfony\Component\Messenger\Stamp\RedeliveryStamp::__construct()" is deprecated, pass "$redeliveredAt" as second argument instead.');
        $redeliveredAt = new \DateTimeImmutable('+2minutes');
        $stamp = new RedeliveryStamp(10, null, null, $redeliveredAt);
        self::assertSame($redeliveredAt, $stamp->getRedeliveredAt());
    }

    /**
     * @group legacy
     */
    public function testPassingBothLegacyAndCurrentRedeliveryAt()
    {
        $this->expectDeprecation('Since symfony/messenger 5.2: Using the "$redeliveredAt" as 4th argument of the "Symfony\Component\Messenger\Stamp\RedeliveryStamp::__construct()" is deprecated, pass "$redeliveredAt" as second argument instead.');
        $redeliveredAt = new \DateTimeImmutable('+2minutes');
        self::expectException(\LogicException::class);
        new RedeliveryStamp(10, $redeliveredAt, null, $redeliveredAt);
    }
}
