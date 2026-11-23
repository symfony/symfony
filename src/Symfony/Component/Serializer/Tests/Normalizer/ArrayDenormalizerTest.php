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

    public function testDenormalizeWithMixedAndTemplateKeyTypes()
    {
        $nestedDenormalizer = $this->createStub(DenormalizerInterface::class);
        $nestedDenormalizer->method('denormalize')->willReturnCallback(static fn ($data) => new ArrayDummy($data['foo'], $data['bar']));

        $denormalizer = new ArrayDenormalizer();
        $denormalizer->setDenormalizer($nestedDenormalizer);

        $input = ['a' => ['foo' => 'one', 'bar' => 'two'], 0 => ['foo' => 'three', 'bar' => 'four']];
        $expected = ['a' => new ArrayDummy('one', 'two'), 0 => new ArrayDummy('three', 'four')];

        $this->assertEquals($expected, $denormalizer->denormalize($input, ArrayDummy::class.'[]', null, ['key_type' => Type::mixed()]));
        $this->assertEquals($expected, $denormalizer->denormalize($input, ArrayDummy::class.'[]', null, ['key_type' => Type::template('TKey')]));
        $this->assertEquals($expected, $denormalizer->denormalize($input, ArrayDummy::class.'[]', null, ['key_type' => Type::template('TKey', Type::union(Type::int(), Type::string()))]));
        $this->assertEquals(['a' => new ArrayDummy('one', 'two')], $denormalizer->denormalize(['a' => ['foo' => 'one', 'bar' => 'two']], ArrayDummy::class.'[]', null, ['key_type' => Type::template('TKey', Type::string())]));

        $this->expectException(NotNormalizableValueException::class);

        $denormalizer->denormalize([0 => ['foo' => 'one', 'bar' => 'two']], ArrayDummy::class.'[]', null, ['key_type' => Type::template('TKey', Type::string())]);
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
