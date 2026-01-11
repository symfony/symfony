<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\UnserializableKeyException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\Store\RedisStore;

/**
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
class KeyTest extends TestCase
{
    public function testSerialize()
    {
        $key = new Key(__METHOD__, 5, 2);
        $key->setState(RedisStore::class, base64_encode(random_bytes(32)));

        $copy = unserialize(serialize($key));
        $this->assertSame(5, $copy->getLimit());
        $this->assertSame(2, $copy->getWeight());
        $this->assertEquals($key->getState(RedisStore::class), $copy->getState(RedisStore::class));
        $this->assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }

    public function testCannotSerializeUnserializableKey()
    {
        $key = new Key(__METHOD__, 1);
        $key->markUnserializable();

        $this->expectException(UnserializableKeyException::class);
        serialize($key);
    }
}
