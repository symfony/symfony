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
use Symfony\Component\Cache\Adapter\RedisAdapterFactory;

class RedisAdapterFactoryTest extends TestCase
{
    #[DataProvider('provideSupportedDsn')]
    public function testSupports(bool $expected, string $dsn)
    {
        $this->assertSame($expected, (new RedisAdapterFactory())->supports($dsn));
    }

    public static function provideSupportedDsn(): iterable
    {
        yield [true, 'redis://localhost'];
        yield [true, 'rediss://localhost'];
        yield [true, 'valkey://localhost'];
        yield [true, 'valkeys://localhost'];
        yield [true, 'REDIS://localhost']; // scheme matching is case-insensitive
        yield [false, 'memcached://localhost'];
        yield [false, 'mysql://localhost'];
        yield [false, 'cache.adapter.redis'];
    }
}
