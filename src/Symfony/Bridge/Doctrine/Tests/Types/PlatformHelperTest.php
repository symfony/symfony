<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\PlatformHelper;

final class PlatformHelperTest extends TestCase
{
    #[DataProvider('providePlatforms')]
    public function testHasNativeGuidType(AbstractPlatform $platform, bool $expected)
    {
        $this->assertSame($expected, PlatformHelper::hasNativeGuidType($platform));
    }

    public static function providePlatforms(): iterable
    {
        yield 'postgresql' => [new PostgreSQLPlatform(), true];
        yield 'sqlite' => [self::getSqlitePlatform(), false];
        yield 'mysql' => [new MySQLPlatform(), false];
        yield 'mariadb' => [new MariaDBPlatform(), false];
        yield 'sqlserver' => [new SQLServerPlatform(), true];
    }

    private static function getSqlitePlatform(): AbstractPlatform
    {
        if (interface_exists(Exception::class)) {
            // DBAL 4+
            return new SQLitePlatform();
        }

        return new \Doctrine\DBAL\Platforms\SqlitePlatform();
    }
}

// @php-cs-fixer-ignore fully_qualified_strict_types
