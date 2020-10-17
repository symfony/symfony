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
use Symfony\Bridge\Doctrine\Types\UlidBinaryType;
use Symfony\Component\Uid\Ulid;

class UlidBinaryTypeTest extends TestCase
{
    private const DUMMY_ULID = '01EEDQEK6ZAZE93J8KG5B4MBJC';

    private $platform;

    /** @var UlidBinaryType */
    private $type;

    public static function setUpBeforeClass(): void
    {
        Type::addType('ulid_binary', UlidBinaryType::class);
    }

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->platform
            ->method('getBinaryTypeDeclarationSQL')
            ->willReturn('DUMMYBINARY(16)');

        $this->type = Type::getType('ulid_binary');
    }

    public function testUlidConvertsToDatabaseValue()
    {
        $uuid = Ulid::fromString(self::DUMMY_ULID);

        $expected = $uuid->toBinary();
        $actual = $this->type->convertToDatabaseValue($uuid, $this->platform);

        $this->assertEquals($expected, $actual);
    }

    public function testNotSupportedStringUlidConversionToDatabaseValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(self::DUMMY_ULID, $this->platform);
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

    public function testUlidConvertsToPHPValue()
    {
        $uuid = $this->type->convertToPHPValue(self::DUMMY_ULID, $this->platform);

        $this->assertEquals(self::DUMMY_ULID, $uuid->__toString());
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
        $uuid = new Ulid();
        $this->assertSame($uuid, $this->type->convertToPHPValue($uuid, $this->platform));
    }

    public function testGetName()
    {
        $this->assertEquals('ulid_binary', $this->type->getName());
    }

    public function testGetGuidTypeDeclarationSQL()
    {
        $this->assertEquals('DUMMYBINARY(16)', $this->type->getSqlDeclaration(['length' => 36], $this->platform));
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
