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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;

final class DatePointTypeTest extends TestCase
{
    private DatePointType $type;

    public static function setUpBeforeClass(): void
    {
        $name = DatePointType::NAME;
        if (Type::hasType($name)) {
            Type::overrideType($name, DatePointType::class);
        } else {
            Type::addType($name, DatePointType::class);
        }
    }

    protected function setUp(): void
    {
        if (!class_exists(DatePoint::class)) {
            self::markTestSkipped('The DatePoint class is not available.');
        }
        $this->type = Type::getType(DatePointType::NAME);
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
        $actual = $this->type->convertToPHPValue($datePoint, new SqlitePlatform());

        $this->assertSame($datePoint, $actual);
    }

    public function testNullConvertsToPHPValue()
    {
        $actual = $this->type->convertToPHPValue(null, new SqlitePlatform());

        $this->assertNull($actual);
    }

    public function testDateTimeImmutableConvertsToPHPValue()
    {
        $format = 'Y-m-d H:i:s';
        $dateTime = new \DateTimeImmutable('2025-03-03 12:13:14');
        $actual = $this->type->convertToPHPValue($dateTime, new SqlitePlatform());
        $expected = DatePoint::createFromInterface($dateTime);

        $this->assertSame($expected->format($format), $actual->format($format));
    }

    public function testGetName()
    {
        $this->assertSame('date_point', $this->type->getName());
    }
}
