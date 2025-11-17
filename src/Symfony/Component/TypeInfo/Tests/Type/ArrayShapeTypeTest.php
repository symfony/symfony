<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Type;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;

class ArrayShapeTypeTest extends TestCase
{
    #[DataProvider('cannotConstructWithInvalidExtraDataProvider')]
    public function testCannotConstructWithInvalidExtra(string $expectedMessage, ?Type $extraKeyType, ?Type $extraValueType)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new ArrayShapeType(
            shape: [1 => ['type' => Type::bool(), 'optional' => false]],
            extraKeyType: $extraKeyType,
            extraValueType: $extraValueType,
        );
    }

    /**
     * @return iterable<array{0: string, 1: ?Type, 2: ?Type}>
     */
    public static function cannotConstructWithInvalidExtraDataProvider(): iterable
    {
        yield ['You must provide a value for "$extraValueType" when "$extraKeyType" is provided.', Type::string(), null];
        yield ['You must provide a value for "$extraKeyType" when "$extraValueType" is provided.', null, Type::string()];
        yield ['"float" is not a valid array key type.', Type::float(), Type::string()];
    }

    public function testGetCollectionKeyType()
    {
        $type = new ArrayShapeType([
            1 => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertEquals(Type::int(), $type->getCollectionKeyType());

        $type = new ArrayShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertEquals(Type::string(), $type->getCollectionKeyType());

        $type = new ArrayShapeType([
            1 => ['type' => Type::bool(), 'optional' => false],
            'foo' => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertEquals(Type::arrayKey(), $type->getCollectionKeyType());
    }

    public function testGetCollectionValueType()
    {
        $type = new ArrayShapeType([
            1 => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertEquals(Type::bool(), $type->getCollectionValueType());

        $type = new ArrayShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::int(), 'optional' => false],
        ]);
        $this->assertEquals(Type::union(Type::int(), Type::bool()), $type->getCollectionValueType());

        $type = new ArrayShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::nullable(Type::string()), 'optional' => false],
        ]);
        $this->assertEquals(Type::nullable(Type::union(Type::bool(), Type::string())), $type->getCollectionValueType());

        $type = new ArrayShapeType([
            'foo' => ['type' => Type::true(), 'optional' => false],
            'bar' => ['type' => Type::false(), 'optional' => false],
        ]);
        $this->assertEquals(Type::bool(), $type->getCollectionValueType());
    }

    public function testAccepts()
    {
        $type = new ArrayShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ]);

        $this->assertFalse($type->accepts('string'));
        $this->assertFalse($type->accepts([]));
        $this->assertFalse($type->accepts(['foo' => 'string']));
        $this->assertFalse($type->accepts(['foo' => true, 'other' => 'string']));

        $this->assertTrue($type->accepts(['foo' => true]));
        $this->assertTrue($type->accepts(['foo' => true, 'bar' => 'string']));

        $type = new ArrayShapeType(
            shape: ['foo' => ['type' => Type::bool()]],
            extraKeyType: Type::string(),
            extraValueType: Type::string(),
        );

        $this->assertTrue($type->accepts(['foo' => true, 'other' => 'string']));
        $this->assertTrue($type->accepts(['other' => 'string', 'foo' => true]));
        $this->assertFalse($type->accepts(['other' => 1, 'foo' => true]));
        $this->assertFalse($type->accepts(['other' => 'string', 'foo' => 'foo']));
    }

    public function testToString()
    {
        $type = new ArrayShapeType([1 => ['type' => Type::bool(), 'optional' => false]]);
        $this->assertSame('array{1: bool}', (string) $type);

        $type = new ArrayShapeType([
            2 => ['type' => Type::int(), 'optional' => true],
            1 => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertSame('array{1: bool, 2?: int}', (string) $type);

        $type = new ArrayShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ]);
        $this->assertSame("array{'bar'?: string, 'foo': bool}", (string) $type);

        $type = new ArrayShapeType(
            shape: ['foo' => ['type' => Type::bool()]],
            extraKeyType: Type::arrayKey(),
            extraValueType: Type::mixed(),
        );
        $this->assertSame("array{'foo': bool, ...}", (string) $type);

        $type = new ArrayShapeType(
            shape: ['foo' => ['type' => Type::bool()]],
            extraKeyType: Type::int(),
            extraValueType: Type::string(),
        );
        $this->assertSame("array{'foo': bool, ...<int, string>}", (string) $type);
    }
}
