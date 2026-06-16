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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\MemcachedAdapterFactory;

class MemcachedAdapterFactoryTest extends TestCase
{
    #[DataProvider('provideSupportedDsn')]
    public function testSupports(bool $expected, string $dsn)
    {
        $this->assertSame($expected, (new MemcachedAdapterFactory())->supports($dsn));
    }

    public static function provideSupportedDsn(): iterable
    {
        yield [true, 'memcached://localhost'];
        yield [true, 'memcached://user:pass@localhost:11211'];
        yield [true, 'MEMCACHED://localhost']; // scheme matching is case-insensitive
        yield [false, 'redis://localhost'];
        yield [false, 'cache.adapter.memcached'];
    }
}
