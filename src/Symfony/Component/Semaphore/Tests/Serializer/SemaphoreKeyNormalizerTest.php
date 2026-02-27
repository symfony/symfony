<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\Serializer\SemaphoreKeyNormalizer;
use Symfony\Component\Semaphore\Store\RedisStore;

class SemaphoreKeyNormalizerTest extends TestCase
{
    public function testNormalizeAndDenormalize()
    {
        $key = new Key(__METHOD__, 5, 2);
        $key->setState(RedisStore::class, base64_encode(random_bytes(32)));
        $key->reduceLifetime(1);

        $normalizer = new SemaphoreKeyNormalizer();
        $copy = $normalizer->denormalize($normalizer->normalize($key), Key::class);

        $this->assertSame(5, $copy->getLimit());
        $this->assertSame(2, $copy->getWeight());
        $this->assertSame($key->getState(RedisStore::class), $copy->getState(RedisStore::class));
        $this->assertEqualsWithDelta($key->getRemainingLifetime(), $copy->getRemainingLifetime(), 0.001);
    }
}
