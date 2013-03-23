<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\CacheDriver;

use Symfony\Component\Cache\Driver\MemoryDriver;

class MemoryCacheDriverTest extends AbstractCacheDriverTest
{
    public function _getTestDriver()
    {
        return new MemoryDriver();
    }
}