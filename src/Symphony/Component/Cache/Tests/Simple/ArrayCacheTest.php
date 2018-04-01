<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Simple;

use Symphony\Component\Cache\Simple\ArrayCache;

/**
 * @group time-sensitive
 */
class ArrayCacheTest extends CacheTestCase
{
    public function createSimpleCache($defaultLifetime = 0)
    {
        return new ArrayCache($defaultLifetime);
    }
}
