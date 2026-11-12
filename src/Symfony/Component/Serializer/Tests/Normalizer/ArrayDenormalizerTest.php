<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;

class ArrayDenormalizerTest extends TestCase
{
    public function testDenormalize()
    {
        $series = [
            [[['foo' => 'one', 'bar' => 'two']], new ArrayDummy('one', 'two')],
            [[['foo' => 'three', 'bar' => 'four']], new ArrayDummy('three', 'four')],
        ];

        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(function ($data) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, [$data]);

                return $return;
            })
        ;

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);
        $result = $denormalizer->denormalize(
            [
                ['foo' => 'one', 'bar' => 'two'],
                ['foo' => 'three', 'bar' => 'four'],
            ],
            __NAMESPACE__.'\ArrayDummy[]'
        );

        $this->assertEquals(
            [
                new ArrayDummy('one', 'two'),
                new ArrayDummy('three', 'four'),
            ],
            $result
        );
    }

    public function testDenormalizeWithRefinedKeyTypes()
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer->method('denormalize')->willReturnArgument(0);

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertSame([1 => 'a'], $denormalizer->denormalize([1 => 'a'], 'string[]', null, ['key_type' => Type::intRange(1, null)]));
        $this->assertSame(['a' => 'b'], $denormalizer->denormalize(['a' => 'b'], 'string[]', null, ['key_type' => Type::union(Type::literal('a'), Type::literal('b'))]));
        $this->assertSame([-5 => 'a'], $denormalizer->denormalize([-5 => 'a'], 'string[]', null, ['key_type' => Type::intRange(1, null)]));
    }

    public function testDenormalizeChecksTemplateKeyAgainstItsBound()
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer->method('denormalize')->willReturnArgument(0);

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertSame([3 => 'a'], $denormalizer->denormalize([3 => 'a'], 'string[]', null, ['key_type' => Type::template('TKey', Type::int())]));
        $this->assertSame([3 => 'a', 'b' => 'c'], $denormalizer->denormalize([3 => 'a', 'b' => 'c'], 'string[]', null, ['key_type' => Type::template('TKey', Type::union(Type::int(), Type::string()))]));
        $this->assertSame([3 => 'a', 'b' => 'c'], $denormalizer->denormalize([3 => 'a', 'b' => 'c'], 'string[]', null, ['key_type' => Type::template('TKey')]));

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type of the key "b" must be "int" ("string" given).');

        $denormalizer->denormalize(['b' => 'c'], 'string[]', null, ['key_type' => Type::template('TKey', Type::int())]);
    }

    public function testDenormalizeDoesNotCheckUnionKeyWithUncheckableMember()
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer->method('denormalize')->willReturnArgument(0);

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertSame(['b' => 'c'], $denormalizer->denormalize(['b' => 'c'], 'string[]', null, ['key_type' => Type::union(Type::int(), Type::template('TKey'))]));
    }

    #[DataProvider('provideUnionKeyTypesWithMembersThatMatchNoKey')]
    public function testDenormalizeChecksUnionKeyAgainstItsIntAndStringMembers(Type $keyType)
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer->method('denormalize')->willReturnArgument(0);

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertSame([3 => 'a'], $denormalizer->denormalize([3 => 'a'], 'string[]', null, ['key_type' => $keyType]));

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type of the key "s" must be "int" ("string" given).');

        $denormalizer->denormalize(['s' => 'a'], 'string[]', null, ['key_type' => $keyType]);
    }

    public static function provideUnionKeyTypesWithMembersThatMatchNoKey(): iterable
    {
        yield '?int' => [Type::nullable(Type::int())];
        yield 'int|object' => [Type::union(Type::int(), Type::object(\stdClass::class))];
        yield 'int|bool' => [Type::union(Type::int(), Type::bool())];
    }

    public function testDenormalizeRejectsRefinedKeyOfAnotherBuiltinType()
    {
        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($this->createStub(DenormalizerInterface::class));

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type of the key "0" must be "string" ("int" given).');

        $denormalizer->denormalize([0 => 'a'], 'string[]', null, ['key_type' => Type::union(Type::literal('a'), Type::literal('b'))]);
    }

    public function testDenormalizeSplitsObjectToPopulatePerItem()
    {
        $first = new ArrayDummy('one', 'two');
        $nested = [new ArrayDummy('three', 'four')];
        $contexts = [];

        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->exactly(4))
            ->method('denormalize')
            ->willReturnCallback(static function ($data, $type, $format, $context) use (&$contexts) {
                $contexts[] = $context;

                return $data;
            })
        ;

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);
        $denormalizer->denormalize(
            ['a' => [], 'b' => [], 'c' => [], 'd' => []],
            __NAMESPACE__.'\ArrayDummy[]',
            null,
            ['object_to_populate' => ['a' => $first, 'b' => $nested, 'c' => 'scalar']]
        );

        $this->assertSame($first, $contexts[0]['object_to_populate']);
        $this->assertSame($nested, $contexts[1]['object_to_populate']);
        $this->assertArrayNotHasKey('object_to_populate', $contexts[2]);
        $this->assertArrayNotHasKey('object_to_populate', $contexts[3]);
    }

    public function testDenormalizeKeepsObjectToPopulateOutOfListItems()
    {
        $contexts = [];

        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(static function ($data, $type, $format, $context) use (&$contexts) {
                $contexts[] = $context;

                return $data;
            })
        ;

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);
        $denormalizer->denormalize(
            [[], []],
            __NAMESPACE__.'\ArrayDummy[]',
            null,
            ['object_to_populate' => [new ArrayDummy('one', 'two'), new ArrayDummy('three', 'four')]]
        );

        $this->assertArrayNotHasKey('object_to_populate', $contexts[0]);
        $this->assertArrayNotHasKey('object_to_populate', $contexts[1]);
    }

    public function testSupportsValidArray()
    {
        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->once())
            ->method('supportsDenormalization')
            ->with(['foo' => 'one', 'bar' => 'two'], ArrayDummy::class, 'json', ['con' => 'text'])
            ->willReturn(true);
        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertTrue(
            $denormalizer->supportsDenormalization(
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                __NAMESPACE__.'\ArrayDummy[]',
                'json',
                ['con' => 'text']
            )
        );
    }

    public function testSupportsInvalidArray()
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer
            ->method('supportsDenormalization')
            ->willReturn(false);
        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertFalse(
            $denormalizer->supportsDenormalization(
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                __NAMESPACE__.'\InvalidClass[]'
            )
        );
    }

    public function testSupportsNoArray()
    {
        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($this->createStub(DenormalizerInterface::class));

        $this->assertFalse(
            $denormalizer->supportsDenormalization(
                ['foo' => 'one', 'bar' => 'two'],
                ArrayDummy::class
            )
        );
    }

    public function testDenormalizeWithoutDenormalizer()
    {
        $arrayDenormalizer = new ArrayDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayDenormalizer->denormalize([], 'string[]');
    }

    public function testSupportsDenormalizationWithoutDenormalizer()
    {
        $arrayDenormalizer = new ArrayDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayDenormalizer->supportsDenormalization([], 'string[]');
    }

    public function testSupportsEmptyArray()
    {
        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->never())
            ->method('supportsDenormalization');
        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $this->assertTrue(
            $denormalizer->supportsDenormalization([], __NAMESPACE__.'\ArrayDummy[]')
        );
    }
}

class ArrayDummy
{
    public $foo;
    public $bar;

    public function __construct($foo, $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
