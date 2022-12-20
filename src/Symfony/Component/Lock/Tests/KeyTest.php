<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\UnserializableKeyException;
use Symfony\Component\Lock\Key;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class KeyTest extends TestCase
{
    public function testSerialize()
    {
        $key = new Key(__METHOD__);
        $key->reduceLifetime(1);
        $key->setState('foo', 'bar');

        $copy = unserialize(serialize($key));
        self::assertSame($key->getState('foo'), $copy->getState('foo'));
        self::assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }

    public function testUnserialize()
    {
        $key = new Key(__METHOD__);
        $key->markUnserializable();

        self::expectException(UnserializableKeyException::class);
        serialize($key);
    }
}
