<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Item;

/**
 * Interface for caching object
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
interface CacheItemInterface
{
    /**
     * Set the value of the key to store our value under
     *
     * @param string $cacheKey
     *
     * @return CacheItemInterface
     */
    public function setKey($cacheKey);

    /**
     * Get the key of the object
     *
     * @return string
     */
    public function getKey();

    /**
     * Set the value to be stored in the cache
     *
     * @param mixed $cacheValue
     *
     * @return CacheItemInterface
     */
    public function setValue($cacheValue);

    /**
     * Get the value of the object
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Set the TTL value
     *
     * @param int $ttl
     *
     * @return CacheItemInterface
     */
    public function setTtl($ttl);

    /**
     * Get the TTL of the object
     *
     * @return int
     */
    public function getTtl();

    /**
     * Get the remaining time in seconds until the item will expire
     * The implementation should save the expiry time in the item metadata on save event
     * and then retrieve it from the object metadata and substract it from the current time
     * *Note* certain delays can occur as the save event won't be able to provide actual save time during the save time
     *
     * @return int
     */
    public function getRemainingTtl();

    /**
     * Set a metadata value
     *
     * @param string $key
     * @param mixed $value
     *
     * @return CacheItemInterface
     */
    public function setMetadata($key, $value);

    /**
     * Do we have any metadata with the object
     *
     * @param string|null $key
     *
     * @return boolean
     */
    public function hasMetadata($key = null);

    /**
     * Get parameter/key from the metadata
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getMetadata($key = null);

    /**
     * Get the namespace of the cache item
     *
     * @return string
     */
    public function getNamespace();

    /**
     * Set the namespace of the cache driver
     *
     * @param string $namespace
     *
     * @return CacheItemInterface
     */
    public function setNamespace($namespace);

    /**
     * Get the tags of an item
     *
     * @return string[]
     */
    public function getTags();

    /**
     * Set the tags of an item
     *
     * @param array $tags
     *
     * @return CacheItemInterface
     */
    public function setTags(array $tags);

}
