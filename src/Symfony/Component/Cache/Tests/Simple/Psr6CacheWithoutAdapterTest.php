<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;

/**
 * @group time-sensitive
 * @group legacy
 */
class Psr6CacheWithoutAdapterTest extends Psr6CacheTest
{
    protected function createCacheItemPool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new ExternalAdapter($defaultLifetime);
    }
}
