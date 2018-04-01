<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Cache;

use Doctrine\Common\Cache\ArrayCache;
use Symphony\Component\Validator\Mapping\Cache\DoctrineCache;

class DoctrineCacheTest extends AbstractCacheTest
{
    protected function setUp()
    {
        $this->cache = new DoctrineCache(new ArrayCache());
    }
}
