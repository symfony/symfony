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
use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

class DeduplicateStampTest extends TestCase
{
    public function testSerializeUnserialize()
    {
        $stamp = new DeduplicateStamp('id', 300.0, true);

        $this->assertEquals(new Key('id'), $stamp->getKey());
        $this->assertSame(300.0, $stamp->getTtl());
        $this->assertTrue($stamp->onlyDeduplicateInQueue());

        $stamp = unserialize(serialize($stamp));

        $this->assertEquals(new Key('id'), $stamp->getKey());
        $this->assertSame(300.0, $stamp->getTtl());
        $this->assertTrue($stamp->onlyDeduplicateInQueue());

        $stamp->getKey()->markUnserializable();

        $stamp = unserialize(serialize($stamp));

        $this->assertEquals(new Key('id'), $stamp->getKey());
        $this->assertSame(300.0, $stamp->getTtl());
        $this->assertTrue($stamp->onlyDeduplicateInQueue());
    }
}
