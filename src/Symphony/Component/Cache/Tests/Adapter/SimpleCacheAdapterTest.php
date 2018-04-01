<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Adapter;

use Symphony\Component\Cache\Simple\FilesystemCache;
use Symphony\Component\Cache\Adapter\SimpleCacheAdapter;

/**
 * @group time-sensitive
 */
class SimpleCacheAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testPrune' => 'SimpleCache just proxies',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        return new SimpleCacheAdapter(new FilesystemCache(), '', $defaultLifetime);
    }
}
