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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;

final class DatePointTypeTest extends TestCase
{
    private DatePointType $type;

    protected function setUp(): void
    {
        if (!class_exists(DatePoint::class)) {
            self::markTestSkipped('The DatePoint class is not available.');
        }
        $this->type = new DatePointType();
    }

    public function testDatePointConvertsToDatabaseValue()
    {
        $datePoint = new DatePoint('2025-03-03 12:13:14');

        $expected = $datePoint->format('Y-m-d H:i:s');
        $actual = $this->type->convertToDatabaseValue($datePoint, new PostgreSQLPlatform());

        $this->assertSame($expected, $actual);
    }

    public function testDatePointConvertsToPHPValue()
    {
        $datePoint = new DatePoint();
        $actual = $this->type->convertToPHPValue($datePoint, self::getSqlitePlatform());

        $this->assertSame($datePoint, $actual);
    }

    public function testNullConvertsToPHPValue()
    {
        $actual = $this->type->convertToPHPValue(null, self::getSqlitePlatform());

        $this->assertNull($actual);
    }

    public function testDateTimeImmutableConvertsToPHPValue()
    {
        $format = 'Y-m-d H:i:s';
        $dateTime = new \DateTimeImmutable('2025-03-03 12:13:14');
        $actual = $this->type->convertToPHPValue($dateTime, self::getSqlitePlatform());
        $expected = DatePoint::createFromInterface($dateTime);

        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testDatabaseValueConvertsToPHPValue()
    {
        $actual = $this->type->convertToPHPValue('2025-03-03 12:13:14', new PostgreSQLPlatform());

        $this->assertInstanceOf(DatePoint::class, $actual);
        $this->assertSame('2025-03-03 12:13:14', $actual->format('Y-m-d H:i:s'));
    }

    public function testGetName()
    {
        $this->assertSame('date_point', $this->type->getName());
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
