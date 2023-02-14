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
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

// DBAL 2 compatibility
class_exists('Doctrine\DBAL\Platforms\MySqlPlatform');
class_exists('Doctrine\DBAL\Platforms\PostgreSqlPlatform');

final class UuidTypeTest extends TestCase
{
    private const DUMMY_UUID = '9f755235-5a2d-4aba-9605-e9962b312e50';

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
        $this->type = Type::getType('uuid');
    }

    public function testUuidConvertsToDatabaseValue()
    {
        $uuid = Uuid::fromString(self::DUMMY_UUID);

        $expected = $uuid->__toString();
        $actual = $this->type->convertToDatabaseValue($uuid, new PostgreSQLPlatform());

        $this->assertEquals($expected, $actual);
    }

    public function testUuidInterfaceConvertsToNativeUidDatabaseValue()
    {
        $uuid = $this->createMock(AbstractUid::class);

        $uuid
            ->expects($this->once())
            ->method('toRfc4122')
            ->willReturn('foo');

        $actual = $this->type->convertToDatabaseValue($uuid, new PostgreSQLPlatform());

        $this->assertEquals('foo', $actual);
    }

    public function testUuidInterfaceConvertsToBinaryDatabaseValue()
    {
        $uuid = $this->createMock(AbstractUid::class);

        $uuid
            ->expects($this->once())
            ->method('toBinary')
            ->willReturn('foo');

        $actual = $this->type->convertToDatabaseValue($uuid, new MySQLPlatform());

        $this->assertEquals('foo', $actual);
    }

    public function testUuidStringConvertsToDatabaseValue()
    {
        $actual = $this->type->convertToDatabaseValue(self::DUMMY_UUID, new PostgreSQLPlatform());

        $this->assertEquals(self::DUMMY_UUID, $actual);
    }

    public function testNotSupportedTypeConversionForDatabaseValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new \stdClass(), new SqlitePlatform());
    }

    public function testNullConversionForDatabaseValue()
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, new SqlitePlatform()));
    }

    public function testUuidInterfaceConvertsToPHPValue()
    {
        $uuid = $this->createMock(AbstractUid::class);
        $actual = $this->type->convertToPHPValue($uuid, new SqlitePlatform());

        $this->assertSame($uuid, $actual);
    }

    public function testUuidConvertsToPHPValue()
    {
        $uuid = $this->type->convertToPHPValue(self::DUMMY_UUID, new SqlitePlatform());

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertEquals(self::DUMMY_UUID, $uuid->__toString());
    }

    public function testInvalidUuidConversionForPHPValue()
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue('abcdefg', new SqlitePlatform());
    }

    public function testNullConversionForPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, new SqlitePlatform()));
    }

    public function testReturnValueIfUuidForPHPValue()
    {
        $uuid = Uuid::v4();

        $this->assertSame($uuid, $this->type->convertToPHPValue($uuid, new SqlitePlatform()));
    }

    public function testGetName()
    {
        $this->assertEquals('uuid', $this->type->getName());
    }

    /**
     * @dataProvider provideSqlDeclarations
     */
    public function testGetGuidTypeDeclarationSQL(AbstractPlatform $platform, string $expectedDeclaration)
    {
        $this->assertEquals($expectedDeclaration, $this->type->getSqlDeclaration(['length' => 36], $platform));
    }

    public static function provideSqlDeclarations(): array
    {
        return [
            [new PostgreSQLPlatform(), 'UUID'],
            [new SqlitePlatform(), 'BLOB'],
            [new MySQLPlatform(), 'BINARY(16)'],
        ];
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint(new SqlitePlatform()));
    }
}
