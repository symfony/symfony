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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ArrayDenormalizerTest extends TestCase
{
    private ArrayDenormalizer $denormalizer;
    private MockObject&DenormalizerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(DenormalizerInterface::class);
        $this->denormalizer = new ArrayDenormalizer();
        $this->denormalizer->setDenormalizer($this->serializer);
    }

    /**
     * @dataProvider getTestArrays
     */
    public function testDenormalize(array $input, array $expected, string $type, string $format, array $context = [])
    {
        $this->serializer->expects($this->atLeastOnce())
            ->method('denormalize')
            ->willReturnCallback(function ($data, $type, $format, $context) use ($input) {
                $key = (int) trim($context['deserialization_path'], '[]');
                $expected = $input[$key];
                $this->assertSame($expected, $data);

                try {
                    return class_exists($type) ? new $type(...$data) : $data;
                } catch (\Throwable $e) {
                    throw new NotNormalizableValueException($e->getMessage(), $e->getCode(), $e);
                }
            });

        $this->assertEquals($expected, $this->denormalizer->denormalize($input, $type, $format, $context));
    }

    public function testSupportsValidArray()
    {
        $this->serializer->expects($this->once())
            ->method('supportsDenormalization')
            ->with($this->anything(), ArrayDummy::class, 'json', ['con' => 'text'])
            ->willReturn(true);

        $this->assertTrue(
            $this->denormalizer->supportsDenormalization(
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
        $this->serializer->expects($this->any())
            ->method('supportsDenormalization')
            ->willReturn(false);

        $this->assertFalse(
            $this->denormalizer->supportsDenormalization(
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
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization(
                ['foo' => 'one', 'bar' => 'two'],
                ArrayDummy::class
            )
        );
    }

    public static function getTestArrays(): array
    {
        return [
            'array<ArrayDummy>' => [
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                [
                    new ArrayDummy('one', 'two'),
                    new ArrayDummy('three', 'four'),
                ],
                __NAMESPACE__.'\ArrayDummy[]',
                'json',
            ],

            'array<ArrayDummy|UnionDummy|null>' => [
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['baz' => 'three'],
                    null,
                ],
                [
                    new ArrayDummy('one', 'two'),
                    new UnionDummy('three'),
                    null,
                ],
                'mixed[]',
                'json',
                [
                    'value_type' => new Type(
                        Type::BUILTIN_TYPE_ARRAY,
                        collection: true,
                        collectionValueType: [
                            new Type(Type::BUILTIN_TYPE_OBJECT, true, ArrayDummy::class),
                            new Type(Type::BUILTIN_TYPE_OBJECT, class: UnionDummy::class),
                        ]
                    ),
                ],
            ],

            'array<ArrayDummy|string>' => [
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                    'string',
                ],
                [
                    new ArrayDummy('one', 'two'),
                    new ArrayDummy('three', 'four'),
                    'string',
                ],
                'mixed[]',
                'json',
                [
                    'value_type' => new Type(
                        Type::BUILTIN_TYPE_ARRAY,
                        collection: true,
                        collectionValueType: [
                            new Type(Type::BUILTIN_TYPE_OBJECT, class: ArrayDummy::class),
                            new Type(Type::BUILTIN_TYPE_STRING),
                        ]
                    ),
                ],
            ],
        ];
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

class UnionDummy
{
    public $baz;

    public function __construct($baz)
    {
        $this->baz = $baz;
    }
}
