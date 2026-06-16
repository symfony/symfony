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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapterFactory;

class PdoAdapterFactoryTest extends TestCase
{
    #[DataProvider('provideSupportedDsn')]
    public function testSupports(bool $expected, string $dsn)
    {
        $this->assertSame($expected, (new PdoAdapterFactory())->supports($dsn));
    }

    public static function provideSupportedDsn(): iterable
    {
        yield [true, 'mysql:host=localhost'];
        yield [true, 'pgsql:host=localhost'];
        yield [true, 'sqlite::memory:'];
        yield [true, 'sqlsrv:server=localhost'];
        yield [true, 'oci:dbname=localhost'];
        yield [true, 'SQLITE::memory:']; // scheme matching is case-insensitive
        yield [false, 'redis://localhost'];
        yield [false, 'cache.adapter.pdo'];
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCreateAdapter()
    {
        $adapter = (new PdoAdapterFactory())->createAdapter('sqlite::memory:', 'ns', 10);

        $this->assertInstanceOf(PdoAdapter::class, $adapter);
    }
}
