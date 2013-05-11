<?php

namespace Symfony\Component\Cache\Psr;

interface CacheItemInterface
{
    /**
     * Get the key associated with this CacheItem
     *
     * @return string
     */
    public function getKey();

    /**
     * Obtain the value of this cache item
     *
     * @return mixed
     */
    public function getValue();

    /**
     * This boolean value tells us if our cache item is currently in the cache or not
     *
     * @return boolean
     */
    public function isHit();

}
