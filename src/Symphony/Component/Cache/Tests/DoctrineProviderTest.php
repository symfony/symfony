<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests;

use Doctrine\Common\Cache\CacheProvider;
use PHPUnit\Framework\TestCase;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Cache\DoctrineProvider;

class DoctrineProviderTest extends TestCase
{
    public function testProvider()
    {
        $pool = new ArrayAdapter();
        $cache = new DoctrineProvider($pool);

        $this->assertInstanceOf(CacheProvider::class, $cache);

        $key = '{}()/\@:';

        $this->assertTrue($cache->delete($key));
        $this->assertFalse($cache->contains($key));

        $this->assertTrue($cache->save($key, 'bar'));
        $this->assertTrue($cache->contains($key));
        $this->assertSame('bar', $cache->fetch($key));

        $this->assertTrue($cache->delete($key));
        $this->assertFalse($cache->fetch($key));
        $this->assertTrue($cache->save($key, 'bar'));

        $cache->flushAll();
        $this->assertFalse($cache->fetch($key));
        $this->assertFalse($cache->contains($key));
    }
}
