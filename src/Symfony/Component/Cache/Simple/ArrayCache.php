<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Psr\Log\LoggerAwareInterface;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ArrayTrait;
use Symfony\Contracts\Cache\CacheInterface;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" and type-hint for "%s" instead.', ArrayCache::class, ArrayAdapter::class, CacheInterface::class), \E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.3, use ArrayAdapter and type-hint for CacheInterface instead.
 */
class ArrayCache implements Psr16CacheInterface, LoggerAwareInterface, ResettableInterface
{
    use ArrayTrait {
        ArrayTrait::deleteItem as delete;
        ArrayTrait::hasItem as has;
    }

    private $defaultLifetime;

    /**
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(int $defaultLifetime = 0, bool $storeSerialized = true)
    {
        $this->defaultLifetime = $defaultLifetime;
        $this->storeSerialized = $storeSerialized;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        if (!\is_string($key) || !isset($this->expiries[$key])) {
            CacheItem::validateKey($key);
        }
        if (!$isHit = isset($this->expiries[$key]) && ($this->expiries[$key] > microtime(true) || !$this->delete($key))) {
            $this->values[$key] = null;

            return $default;
        }
        if (!$this->storeSerialized) {
            return $this->values[$key];
        }
        $value = $this->unfreeze($key, $isHit);

        return $isHit ? $value : $default;
    }

    /**
     * {@inheritdoc}
     *
     * @return iterable
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given.', \is_object($keys) ? \get_class($keys) : \gettype($keys)));
        }
        foreach ($keys as $key) {
            if (!\is_string($key) || !isset($this->expiries[$key])) {
                CacheItem::validateKey($key);
            }
        }

        return $this->generateItems($keys, microtime(true), function ($k, $v, $hit) use ($default) { return $hit ? $v : $default; });
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function deleteMultiple($keys)
    {
        if (!\is_array($keys) && !$keys instanceof \Traversable) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given.', \is_object($keys) ? \get_class($keys) : \gettype($keys)));
        }
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null)
    {
        if (!\is_string($key)) {
            CacheItem::validateKey($key);
        }

        return $this->setMultiple([$key => $value], $ttl);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!\is_array($values) && !$values instanceof \Traversable) {
            throw new InvalidArgumentException(sprintf('Cache values must be array or Traversable, "%s" given.', \is_object($values) ? \get_class($values) : \gettype($values)));
        }
        $valuesArray = [];

        foreach ($values as $key => $value) {
            if (!\is_int($key) && !(\is_string($key) && isset($this->expiries[$key]))) {
                CacheItem::validateKey($key);
            }
            $valuesArray[$key] = $value;
        }
        if (false === $ttl = $this->normalizeTtl($ttl)) {
            return $this->deleteMultiple(array_keys($valuesArray));
        }
        $expiry = 0 < $ttl ? microtime(true) + $ttl : \PHP_INT_MAX;

        foreach ($valuesArray as $key => $value) {
            if ($this->storeSerialized && null === $value = $this->freeze($value, $key)) {
                return false;
            }
            $this->values[$key] = $value;
            $this->expiries[$key] = $expiry;
        }

        return true;
    }

    private function normalizeTtl($ttl)
    {
        if (null === $ttl) {
            return $this->defaultLifetime;
        }
        if ($ttl instanceof \DateInterval) {
            $ttl = (int) \DateTime::createFromFormat('U', 0)->add($ttl)->format('U');
        }
        if (\is_int($ttl)) {
            return 0 < $ttl ? $ttl : false;
        }

        throw new InvalidArgumentException(sprintf('Expiration date must be an integer, a DateInterval or null, "%s" given.', \is_object($ttl) ? \get_class($ttl) : \gettype($ttl)));
    }
}
