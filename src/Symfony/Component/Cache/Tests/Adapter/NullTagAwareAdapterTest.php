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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\NullTagAwareAdapter;

/**
 * @group time-sensitive
 */
class NullTagAwareAdapterTest extends NullAdapterTest
{
    use TagAwareTestTrait;

    public function createCachePool(): CacheItemPoolInterface
    {
        return new NullTagAwareAdapter();
    }
}
