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
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class RedeliveryStampTest extends TestCase
{
    public function testGetters()
    {
        $stamp = new RedeliveryStamp(10, 'sender_alias');
        $this->assertSame(10, $stamp->getRetryCount());
        $this->assertSame('sender_alias', $stamp->getSenderClassOrAlias());
        $this->assertInstanceOf(\DateTimeInterface::class, $stamp->getRedeliveredAt());
        $this->assertNull($stamp->getExceptionMessage());
        $this->assertNull($stamp->getFlattenException());
    }

    public function testGettersPopulated()
    {
        $flattenException = new FlattenException();
        $stamp = new RedeliveryStamp(10, 'sender_alias', 'exception message', $flattenException);
        $this->assertSame('exception message', $stamp->getExceptionMessage());
        $this->assertSame($flattenException, $stamp->getFlattenException());
    }
}
