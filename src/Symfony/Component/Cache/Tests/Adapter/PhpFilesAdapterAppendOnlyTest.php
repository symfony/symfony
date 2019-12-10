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
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * @group time-sensitive
 */
class PhpFilesAdapterAppendOnlyTest extends PhpFilesAdapterTest
{
    protected $skippedTests = [
        'testDefaultLifeTime' => 'PhpFilesAdapter does not allow configuring a default lifetime.',
        'testExpiration' => 'PhpFilesAdapter in append-only mode does not expiration.',
    ];

    public function createCachePool(): CacheItemPoolInterface
    {
        return new PhpFilesAdapter('sf-cache', 0, null, true);
    }
}
