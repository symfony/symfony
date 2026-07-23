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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
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

    #[DataProvider('provideInvalidUnserializeData')]
    public function testUnserializeRejectsInvariantViolations(array $data)
    {
        $key = (new \ReflectionClass(Key::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $key->__unserialize($data);
    }

    public function testUnserializeRejectsStringableTrampoline()
    {
        $data = ['resource' => new KeyTestToStringGadget(), 'limit' => 1, 'weight' => 1, 'expiringTime' => null, 'state' => []];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(Key::class), Key::class, \count($data));
        foreach ($data as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }
        $payload .= '}';

        KeyTestToStringGadget::$fired = false;

        try {
            unserialize($payload, ['allowed_classes' => [Key::class, KeyTestToStringGadget::class]]);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(KeyTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideInvalidUnserializeData(): iterable
    {
        yield 'limit below 1' => [['resource' => 'r', 'limit' => 0, 'weight' => 1, 'expiringTime' => null, 'state' => []]];
        yield 'weight below 1' => [['resource' => 'r', 'limit' => 2, 'weight' => 0, 'expiringTime' => null, 'state' => []]];
        yield 'weight above limit' => [['resource' => 'r', 'limit' => 2, 'weight' => 3, 'expiringTime' => null, 'state' => []]];
    }
}

class KeyTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}
