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
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

class SentToFailureTransportStampTest extends TestCase
{
    public function testGetters()
    {
        $flattenException = new FlattenException();
        $stamp = new SentToFailureTransportStamp(
            'exception message',
            'original_receiver',
            $flattenException
        );
        $this->assertSame('exception message', $stamp->getExceptionMessage());
        $this->assertSame('original_receiver', $stamp->getOriginalReceiverName());
        $this->assertSame($flattenException, $stamp->getFlattenException());
        $this->assertInstanceOf(\DateTimeInterface::class, $stamp->getSentAt());
    }
}
