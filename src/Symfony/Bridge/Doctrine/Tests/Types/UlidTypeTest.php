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
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;

final class UlidTypeTest extends TestCase
{
    private const DUMMY_ULID = '01EEDQEK6ZAZE93J8KG5B4MBJC';

    /** @var AbstractPlatform */
    private $platform;

    /** @var UlidType */
    private $type;

    public static function setUpBeforeClass(): void
    {
        if (Type::hasType('ulid')) {
            Type::overrideType('ulid', UlidType::class);
        } else {
            Type::addType('ulid', UlidType::class);
        }
    }

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->platform
            ->method('hasNativeGuidType')
            ->willReturn(true);
        $this->platform
            ->method('getGuidTypeDeclarationSQL')
            ->willReturn('DUMMYVARCHAR()');

        $this->type = Type::getType('ulid');
    }

    public function testUlidConvertsToDatabaseValue()
    {
        $ulid = Ulid::fromString(self::DUMMY_ULID);

        $expected = $ulid->toRfc4122();
        $actual = $this->type->convertToDatabaseValue($ulid, $this->platform);

        $this->assertEquals($expected, $actual);
    }

    public function testUlidInterfaceConvertsToDatabaseValue()
    {
        $ulid = $this->createMock(AbstractUid::class);

        $ulid
            ->expects($this->once())
            ->method('toRfc4122')
            ->willReturn('foo');

        $actual = $this->type->convertToDatabaseValue($ulid, $this->platform);

        $this->assertEquals('foo', $actual);
    }

    public function testUlidStringConvertsToDatabaseValue()
    {
        $actual = $this->type->convertToDatabaseValue(self::DUMMY_ULID, $this->platform);
        $ulid = Ulid::fromString(self::DUMMY_ULID);

        $expected = $ulid->toRfc4122();

        $this->assertEquals($expected, $actual);
    }

    public function testNotSupportedTypeConversionForDatabaseValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new \stdClass(), $this->platform);
    }

    public function testNullConversionForDatabaseValue()
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testUlidInterfaceConvertsToPHPValue()
    {
        $ulid = $this->createMock(AbstractUid::class);
        $actual = $this->type->convertToPHPValue($ulid, $this->platform);

        $this->assertSame($ulid, $actual);
    }

    public function testUlidConvertsToPHPValue()
    {
        $ulid = $this->type->convertToPHPValue(self::DUMMY_ULID, $this->platform);

        $this->assertInstanceOf(Ulid::class, $ulid);
        $this->assertEquals(self::DUMMY_ULID, $ulid->__toString());
    }

    public function testInvalidUlidConversionForPHPValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversionForPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testReturnValueIfUlidForPHPValue()
    {
        $ulid = new Ulid();

        $this->assertSame($ulid, $this->type->convertToPHPValue($ulid, $this->platform));
    }

    public function testGetName()
    {
        $this->assertEquals('ulid', $this->type->getName());
    }

    public function testGetGuidTypeDeclarationSQL()
    {
        $this->assertEquals('DUMMYVARCHAR()', $this->type->getSqlDeclaration(['length' => 36], $this->platform));
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
