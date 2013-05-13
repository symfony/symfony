<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
