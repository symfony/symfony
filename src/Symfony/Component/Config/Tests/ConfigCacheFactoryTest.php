<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheInterface;

class ConfigCacheFactoryTest extends TestCase
{
    public function testCanCreateCache()
    {
        $cacheFactory = new ConfigCacheFactory(true);

        $cache = $cacheFactory->cache('file', function (ConfigCacheInterface $cache) {
            return;
        });

        $this->assertInstanceOf(ConfigCacheInterface::class, $cache);
    }
}
