<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\ExpiredSignatureStorage;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\InMemoryUser;

class SignatureHasherTest extends TestCase
{
    public function testVerifySignatureHashRejectsAConcurrentUsageBeyondMaxUses()
    {
        $user = new InMemoryUser('john', 'pa$$word');
        $expires = time() + 500;
        $hasher = null;
        $hash = null;

        // the same hash is verified a second time while the first verification reads the usage counter
        $cache = new ConcurrentUsageCache(new ArrayAdapter(), static function () use (&$hasher, &$hash, $user, $expires) {
            $hasher->verifySignatureHash($user, $expires, $hash);
        });
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 's3cret', new ExpiredSignatureStorage($cache, 360), 1);
        $hash = $hasher->computeSignatureHash($user, $expires);

        $this->expectException(ExpiredSignatureException::class);
        $hasher->verifySignatureHash($user, $expires, $hash);
    }
}

class ConcurrentUsageCache implements CacheItemPoolInterface
{
    private $pool;
    private $concurrentUsage;

    public function __construct(CacheItemPoolInterface $pool, callable $concurrentUsage)
    {
        $this->pool = $pool;
        $this->concurrentUsage = \Closure::fromCallable($concurrentUsage);
    }

    public function getItem($key): CacheItemInterface
    {
        if ($concurrentUsage = $this->concurrentUsage) {
            $this->concurrentUsage = null;
            $concurrentUsage();
        }

        return $this->pool->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    public function hasItem($key): bool
    {
        return $this->pool->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    public function deleteItem($key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->pool->commit();
    }
}
