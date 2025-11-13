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
use Symfony\Bridge\Doctrine\Types\TimePointType;
use Symfony\Component\Clock\DatePoint;

final class TimePointTypeTest extends TestCase
{
    private TimePointType $type;

    public static function setUpBeforeClass(): void
    {
        $name = TimePointType::NAME;
        if (Type::hasType($name)) {
            Type::overrideType($name, TimePointType::class);
        } else {
            Type::addType($name, TimePointType::class);
        }
    }

    protected function setUp(): void
    {
        if (!class_exists(DatePoint::class)) {
            self::markTestSkipped('The DatePoint class is not available.');
        }
        $this->type = Type::getType(TimePointType::NAME);
    }

    public function testDatePointConvertsToDatabaseValue()
    {
        $datePoint = DatePoint::createFromFormat('!H:i:s', '05:10:15');

        $expected = $datePoint->format('H:i:s');
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
        $time = '05:10:15';
        $dateTime = \DateTimeImmutable::createFromFormat('!H:i:s', $time);
        $actual = $this->type->convertToPHPValue($dateTime, self::getSqlitePlatform());
        $expected = DatePoint::createFromFormat('!H:i:s', $time);

        $this->assertInstanceOf(DatePoint::class, $actual);
        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testDatabaseValueConvertsToPHPValue()
    {
        $format = 'Y-m-d H:i:s.u';
        $time = '05:10:15';
        $actual = $this->type->convertToPHPValue($time, new PostgreSQLPlatform());
        $expected = DatePoint::createFromFormat('!H:i:s', $time);

        $this->assertInstanceOf(DatePoint::class, $actual);
        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testGetName()
    {
        $this->assertSame('time_point', $this->type->getName());
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
