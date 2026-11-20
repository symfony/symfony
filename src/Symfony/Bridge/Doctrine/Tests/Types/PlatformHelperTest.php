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

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\PlatformHelper;

// DBAL 2 compatibility
class_exists('Doctrine\DBAL\Platforms\MySqlPlatform');
class_exists('Doctrine\DBAL\Platforms\PostgreSqlPlatform');
// DBAL 3 compatibility
class_exists('Doctrine\DBAL\Platforms\SqlitePlatform');

final class PlatformHelperTest extends TestCase
{
    /**
     * @dataProvider providePlatforms
     */
    public function testHasNativeGuidType(AbstractPlatform $platform, bool $expected)
    {
        $this->assertSame($expected, PlatformHelper::hasNativeGuidType($platform));
    }

    public static function providePlatforms(): iterable
    {
        yield 'postgresql' => [new PostgreSQLPlatform(), true];
        yield 'sqlite' => [new SQLitePlatform(), false];
        yield 'mysql' => [new MySQLPlatform(), false];

        if (class_exists(MariaDBPlatform::class)) {
            yield 'mariadb' => [new MariaDBPlatform(), false];
        }

        if (!(new \ReflectionClass(SQLServerPlatform::class))->isAbstract()) {
            yield 'sqlserver' => [new SQLServerPlatform(), true];
        }
    }
}
