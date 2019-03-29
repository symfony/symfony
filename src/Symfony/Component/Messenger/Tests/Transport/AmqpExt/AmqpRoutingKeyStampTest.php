<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpRoutingKeyStamp;

class AmqpRoutingKeyStampTest extends TestCase
{
    public function testStamp()
    {
        $stamp = new AmqpRoutingKeyStamp('routing_key');
        $this->assertSame('routing_key', $stamp->getRoutingKey());
    }
}
