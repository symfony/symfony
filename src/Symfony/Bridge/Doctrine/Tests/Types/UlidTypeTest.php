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
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;

// DBAL 2 compatibility
class_exists('Doctrine\DBAL\Platforms\PostgreSqlPlatform');

final class UlidTypeTest extends TestCase
{
    private const DUMMY_ULID = '01EEDQEK6ZAZE93J8KG5B4MBJC';

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
        $this->type = Type::getType('ulid');
    }

    public function testUlidConvertsToDatabaseValue()
    {
        $ulid = Ulid::fromString(self::DUMMY_ULID);

        $expected = $ulid->toRfc4122();
        $actual = $this->type->convertToDatabaseValue($ulid, new PostgreSQLPlatform());

        self::assertEquals($expected, $actual);
    }

    public function testUlidInterfaceConvertsToDatabaseValue()
    {
        $ulid = self::createMock(AbstractUid::class);

        $ulid
            ->expects(self::once())
            ->method('toRfc4122')
            ->willReturn('foo');

        $actual = $this->type->convertToDatabaseValue($ulid, new PostgreSQLPlatform());

        self::assertEquals('foo', $actual);
    }

    public function testUlidStringConvertsToDatabaseValue()
    {
        $actual = $this->type->convertToDatabaseValue(self::DUMMY_ULID, new PostgreSQLPlatform());
        $ulid = Ulid::fromString(self::DUMMY_ULID);

        $expected = $ulid->toRfc4122();

        self::assertEquals($expected, $actual);
    }

    public function testNotSupportedTypeConversionForDatabaseValue()
    {
        self::expectException(ConversionException::class);

        $this->type->convertToDatabaseValue(new \stdClass(), new SqlitePlatform());
    }

    public function testNullConversionForDatabaseValue()
    {
        self::assertNull($this->type->convertToDatabaseValue(null, new SqlitePlatform()));
    }

    public function testUlidInterfaceConvertsToPHPValue()
    {
        $ulid = self::createMock(AbstractUid::class);
        $actual = $this->type->convertToPHPValue($ulid, new SqlitePlatform());

        self::assertSame($ulid, $actual);
    }

    public function testUlidConvertsToPHPValue()
    {
        $ulid = $this->type->convertToPHPValue(self::DUMMY_ULID, new SqlitePlatform());

        self::assertInstanceOf(Ulid::class, $ulid);
        self::assertEquals(self::DUMMY_ULID, $ulid->__toString());
    }

    public function testInvalidUlidConversionForPHPValue()
    {
        self::expectException(ConversionException::class);

        $this->type->convertToPHPValue('abcdefg', new SqlitePlatform());
    }

    public function testNullConversionForPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, new SqlitePlatform()));
    }

    public function testReturnValueIfUlidForPHPValue()
    {
        $ulid = new Ulid();

        self::assertSame($ulid, $this->type->convertToPHPValue($ulid, new SqlitePlatform()));
    }

    public function testGetName()
    {
        self::assertEquals('ulid', $this->type->getName());
    }

    /**
     * @dataProvider provideSqlDeclarations
     */
    public function testGetGuidTypeDeclarationSQL(AbstractPlatform $platform, string $expectedDeclaration)
    {
        self::assertEquals($expectedDeclaration, $this->type->getSqlDeclaration(['length' => 36], $platform));
    }

    public function provideSqlDeclarations(): array
    {
        return [
            [new PostgreSQLPlatform(), 'UUID'],
            [new SqlitePlatform(), 'BLOB'],
            [new MySQLPlatform(), 'BINARY(16)'],
        ];
    }

    public function testRequiresSQLCommentHint()
    {
        self::assertTrue($this->type->requiresSQLCommentHint(new SqlitePlatform()));
    }
}
