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
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

final class UuidTypeTest extends TestCase
{
    private const DUMMY_UUID = '9f755235-5a2d-4aba-9605-e9962b312e50';

    /** @var AbstractPlatform */
    private $platform;

    /** @var UuidType */
    private $type;

    public static function setUpBeforeClass(): void
    {
        if (Type::hasType('uuid')) {
            Type::overrideType('uuid', UuidType::class);
        } else {
            Type::addType('uuid', UuidType::class);
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

        $this->type = Type::getType('uuid');
    }

    public function testUuidConvertsToDatabaseValue()
    {
        $uuid = Uuid::fromString(self::DUMMY_UUID);

        $expected = $uuid->__toString();
        $actual = $this->type->convertToDatabaseValue($uuid, $this->platform);

        $this->assertEquals($expected, $actual);
    }

    public function testUuidInterfaceConvertsToDatabaseValue()
    {
        $uuid = $this->createMock(AbstractUid::class);

        $uuid
            ->expects($this->once())
            ->method('toRfc4122')
            ->willReturn('foo');

        $actual = $this->type->convertToDatabaseValue($uuid, $this->platform);

        $this->assertEquals('foo', $actual);
    }

    public function testUuidStringConvertsToDatabaseValue()
    {
        $actual = $this->type->convertToDatabaseValue(self::DUMMY_UUID, $this->platform);

        $this->assertEquals(self::DUMMY_UUID, $actual);
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

    public function testUuidInterfaceConvertsToPHPValue()
    {
        $uuid = $this->createMock(AbstractUid::class);
        $actual = $this->type->convertToPHPValue($uuid, $this->platform);

        $this->assertSame($uuid, $actual);
    }

    public function testUuidConvertsToPHPValue()
    {
        $uuid = $this->type->convertToPHPValue(self::DUMMY_UUID, $this->platform);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertEquals(self::DUMMY_UUID, $uuid->__toString());
    }

    public function testInvalidUuidConversionForPHPValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversionForPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testReturnValueIfUuidForPHPValue()
    {
        $uuid = Uuid::v4();

        $this->assertSame($uuid, $this->type->convertToPHPValue($uuid, $this->platform));
    }

    public function testGetName()
    {
        $this->assertEquals('uuid', $this->type->getName());
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
