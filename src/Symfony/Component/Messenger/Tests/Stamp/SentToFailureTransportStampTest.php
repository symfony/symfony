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
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

class SentToFailureTransportStampTest extends TestCase
{
    public function testGetOriginalReceiverName()
    {
        $stamp = new SentToFailureTransportStamp('original_receiver');
        $this->assertSame('original_receiver', $stamp->getOriginalReceiverName());
    }
}
