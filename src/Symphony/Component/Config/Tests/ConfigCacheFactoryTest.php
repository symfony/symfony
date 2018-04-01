<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Config\ConfigCacheFactory;

class ConfigCacheFactoryTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid type for callback argument. Expected callable, but got "object".
     */
    public function testCacheWithInvalidCallback()
    {
        $cacheFactory = new ConfigCacheFactory(true);

        $cacheFactory->cache('file', new \stdClass());
    }
}
