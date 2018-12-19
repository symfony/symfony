<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\SimpleCacheAdapter;
use Symfony\Component\Cache\Simple\Psr6Cache;

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
        return new SimpleCacheAdapter(new Psr6Cache(new FilesystemAdapter()), '', $defaultLifetime);
    }
}
