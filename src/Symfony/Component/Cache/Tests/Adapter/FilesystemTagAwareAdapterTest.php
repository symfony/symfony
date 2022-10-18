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
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

/**
 * @group time-sensitive
 */
class FilesystemTagAwareAdapterTest extends FilesystemAdapterTest
{
    use TagAwareTestTrait;

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new FilesystemTagAwareAdapter('', $defaultLifetime);
    }
}
