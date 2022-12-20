<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use Doctrine\Common\Cache\CacheProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

/**
 * @group legacy
 */
class DoctrineProviderTest extends TestCase
{
    public function testProvider()
    {
        $pool = new ArrayAdapter();
        $cache = new DoctrineProvider($pool);

        self::assertInstanceOf(CacheProvider::class, $cache);

        $key = '{}()/\@:';

        self::assertTrue($cache->delete($key));
        self::assertFalse($cache->contains($key));

        self::assertTrue($cache->save($key, 'bar'));
        self::assertTrue($cache->contains($key));
        self::assertSame('bar', $cache->fetch($key));

        self::assertTrue($cache->delete($key));
        self::assertFalse($cache->fetch($key));
        self::assertTrue($cache->save($key, 'bar'));

        $cache->flushAll();
        self::assertFalse($cache->fetch($key));
        self::assertFalse($cache->contains($key));
    }
}
