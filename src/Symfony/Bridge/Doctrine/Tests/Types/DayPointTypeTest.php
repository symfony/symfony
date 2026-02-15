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
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\DayPointType;
use Symfony\Component\Clock\DatePoint;

final class DayPointTypeTest extends TestCase
{
    private DayPointType $type;

    public static function setUpBeforeClass(): void
    {
        $name = DayPointType::NAME;
        if (Type::hasType($name)) {
            Type::overrideType($name, DayPointType::class);
        } else {
            Type::addType($name, DayPointType::class);
        }
    }

    protected function setUp(): void
    {
        if (!class_exists(DatePoint::class)) {
            self::markTestSkipped('The DatePoint class is not available.');
        }
        $this->type = Type::getType(DayPointType::NAME);
    }

    public function testDatePointConvertsToDatabaseValue()
    {
        $datePoint = DatePoint::createFromFormat('!Y-m-d', '2025-03-03');

        $expected = $datePoint->format('Y-m-d');
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
        $format = 'Y-m-d H:i:s.u';
        $date = '2025-03-03';
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $actual = $this->type->convertToPHPValue($dateTime, self::getSqlitePlatform());
        $expected = DatePoint::createFromFormat('!Y-m-d', $date);

        $this->assertInstanceOf(DatePoint::class, $actual);
        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testDatabaseValueConvertsToPHPValue()
    {
        $format = 'Y-m-d H:i:s.u';
        $date = '2025-03-03';
        $actual = $this->type->convertToPHPValue($date, new PostgreSQLPlatform());
        $expected = DatePoint::createFromFormat('!Y-m-d', $date);

        $this->assertInstanceOf(DatePoint::class, $actual);
        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testGetName()
    {
        $this->assertSame('day_point', $this->type->getName());
    }

    private static function getSqlitePlatform(): AbstractPlatform
    {
        if (interface_exists(Exception::class)) {
            // DBAL 4+
            return new \Doctrine\DBAL\Platforms\SQLitePlatform();
        }

        return new \Doctrine\DBAL\Platforms\SqlitePlatform();
    }
}
