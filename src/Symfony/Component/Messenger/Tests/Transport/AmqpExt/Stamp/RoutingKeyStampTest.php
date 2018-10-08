<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\AmqpExt\Stamp\RoutingKeyStamp;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class RoutingKeyStampTest extends TestCase
{
    public function testSerializable()
    {
        $stamp = new RoutingKeyStamp('dummy_routing');

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
