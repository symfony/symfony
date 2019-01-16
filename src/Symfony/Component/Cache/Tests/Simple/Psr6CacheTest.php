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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Simple\Psr6Cache;

/**
 * @group time-sensitive
 */
class Psr6CacheTest extends CacheTestCase
{
    protected $skippedTests = [
        'testPrune' => 'Psr6Cache just proxies',
    ];

    public function createSimpleCache($defaultLifetime = 0)
    {
        return new Psr6Cache(new FilesystemAdapter('', $defaultLifetime));
    }
}
