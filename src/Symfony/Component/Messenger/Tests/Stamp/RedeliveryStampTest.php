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
        $stamp = new RedeliveryStamp(10, 'sender_alias');
        $this->assertSame(10, $stamp->getRetryCount());
        $this->assertSame('sender_alias', $stamp->getSenderClassOrAlias());
    }
}
