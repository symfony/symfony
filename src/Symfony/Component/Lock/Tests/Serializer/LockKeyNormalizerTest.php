<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\UnserializableKeyException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Serializer\LockKeyNormalizer;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class LockKeyNormalizerTest extends TestCase
{
    public function testNormalizeAndDenormalize()
    {
        $key = new Key(__METHOD__);
        $key->reduceLifetime(1);
        $key->setState('foo', 'bar');
        $normalizer = new LockKeyNormalizer();

        $copy = $normalizer->denormalize($normalizer->normalize($key), Key::class);
        $this->assertSame($key->getState('foo'), $copy->getState('foo'));
        $this->assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }

    public function testDenormalizeXml()
    {
        $serializer = new Serializer([new LockKeyNormalizer()], [new XmlEncoder()]);

        $copy = $serializer->deserialize($serializer->serialize(new Key(__METHOD__), 'xml'), Key::class, 'xml');
        $this->assertSame(__METHOD__, (string) $copy);
        $this->assertNull($copy->getRemainingLifetime());

        $key = new Key(__METHOD__);
        $key->reduceLifetime(1);
        $key->setState(InMemoryStore::class, 'token');

        $copy = $serializer->deserialize($serializer->serialize($key, 'xml'), Key::class, 'xml');
        $this->assertSame('token', $copy->getState(InMemoryStore::class));
        $this->assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }

    public function testNormalizingUnserializableLockThrows()
    {
        $key = new Key(__METHOD__);
        $key->markUnserializable();
        $normalizer = new LockKeyNormalizer();

        $this->expectException(UnserializableKeyException::class);
        $normalizer->normalize($key);
    }
}
