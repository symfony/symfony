<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::CALLBACKS.
 */
trait CallbacksTestTrait
{
    abstract protected function getNormalizerForCallbacks(): NormalizerInterface;

    abstract protected function getNormalizerForCallbacksWithPropertyTypeExtractor(): NormalizerInterface;

    /**
     * @dataProvider provideNormalizeCallbacks
     */
    public function testNormalizeCallbacks($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = new CallbacksObject();
        $obj->bar = $valueBar;

        $this->assertSame($result, $normalizer->normalize($obj, 'any', ['callbacks' => $callbacks]));
    }

    /**
     * @dataProvider provideNormalizeCallbacks
     */
    public function testNormalizeCallbacksWithTypedProperty($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $obj = new CallbacksObject();
        $obj->bar = $valueBar;

        $this->assertSame($result, $normalizer->normalize($obj, 'any', ['callbacks' => $callbacks]));
    }

    /**
     * @dataProvider provideNormalizeCallbacks
     */
    public function testNormalizeCallbacksWithNoConstructorArgument($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $obj = new class() extends CallbacksObject {
            public function __construct()
            {
            }
        };

        $obj->bar = $valueBar;

        $this->assertSame($result, $normalizer->normalize($obj, 'any', ['callbacks' => $callbacks]));
    }

    /**
     * @dataProvider provideDenormalizeCallbacks
     */
    public function testDenormalizeCallbacks($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = $normalizer->denormalize(['bar' => $valueBar], CallbacksObject::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof(CallbacksObject::class, $obj);
        $this->assertEquals($result, $obj);
    }

    /**
     * @dataProvider providerDenormalizeCallbacksWithTypedProperty
     */
    public function testDenormalizeCallbacksWithTypedProperty($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $obj = $normalizer->denormalize(['foo' => $valueBar], CallbacksObject::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof(CallbacksObject::class, $obj);
        $this->assertEquals($result, $obj);
    }

    /**
     * @dataProvider providerDenormalizeCallbacksWithTypedProperty
     */
    public function testDenormalizeCallbacksWithNoConstructorArgument($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $objWithNoConstructorArgument = new class() extends CallbacksObject {
            public function __construct()
            {
            }
        };

        $obj = $normalizer->denormalize(['foo' => $valueBar], $objWithNoConstructorArgument::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof($objWithNoConstructorArgument::class, $obj);
        $this->assertEquals($result->getBar(), $obj->getBar());
    }

    /**
     * @dataProvider provideInvalidCallbacks
     */
    public function testUncallableCallbacks($callbacks)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = new CallbacksObject();

        $this->markTestSkipped('Callback validation for callbacks in the context has been forgotten. See https://github.com/symfony/symfony/pull/30950');
        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($obj, null, ['callbacks' => $callbacks]);
    }

    public function provideNormalizeCallbacks()
    {
        return [
            'Change a string' => [
                [
                    'bar' => function ($bar) {
                        $this->assertEquals('baz', $bar);

                        return 'baz';
                    },
                ],
                'baz',
                ['bar' => 'baz', 'foo' => null],
            ],
            'Null an item' => [
                [
                    'bar' => function ($value, $object, $attributeName, $format, $context) {
                        $this->assertSame('baz', $value);
                        $this->assertInstanceOf(CallbacksObject::class, $object);
                        $this->assertSame('bar', $attributeName);
                        $this->assertSame('any', $format);
                        $this->assertArrayHasKey('circular_reference_limit_counters', $context);
                    },
                ],
                'baz',
                ['bar' => null, 'foo' => null],
            ],
            'Format a date' => [
                [
                    'bar' => function ($bar) {
                        $this->assertInstanceOf(\DateTime::class, $bar);

                        return $bar->format('d-m-Y H:i:s');
                    },
                ],
                new \DateTime('2011-09-10 06:30:00'),
                ['bar' => '10-09-2011 06:30:00', 'foo' => null],
            ],
            'Collect a property' => [
                [
                    'bar' => function (array $bars) {
                        $result = '';
                        foreach ($bars as $bar) {
                            $result .= $bar->bar;
                        }

                        return $result;
                    },
                ],
                [new CallbacksObject('baz'), new CallbacksObject('quux')],
                ['bar' => 'bazquux', 'foo' => null],
            ],
            'Count a property' => [
                [
                    'bar' => fn (array $bars) => \count($bars),
                ],
                [new CallbacksObject(), new CallbacksObject()],
                ['bar' => 2, 'foo' => null],
            ],
        ];
    }

    public function provideDenormalizeCallbacks(): array
    {
        return [
            'Change a string' => [
                [
                    'bar' => function ($bar) {
                        $this->assertEquals('bar', $bar);

                        return $bar;
                    },
                ],
                'bar',
                new CallbacksObject('bar'),
            ],
            'Null an item' => [
                [
                    'bar' => function ($value, $object, $attributeName, $format, $context) {
                        $this->assertSame('baz', $value);
                        $this->assertTrue(is_a($object, CallbacksObject::class, true));
                        $this->assertSame('bar', $attributeName);
                        $this->assertSame('any', $format);
                        $this->assertIsArray($context);
                    },
                ],
                'baz',
                new CallbacksObject(null),
            ],
            'Format a date' => [
                [
                    'bar' => function ($bar) {
                        $this->assertIsString($bar);

                        return \DateTime::createFromFormat('d-m-Y H:i:s', $bar);
                    },
                ],
                '10-09-2011 06:30:00',
                new CallbacksObject(new \DateTime('2011-09-10 06:30:00')),
            ],
            'Collect a property' => [
                [
                    'bar' => function (array $bars) {
                        $result = '';
                        foreach ($bars as $bar) {
                            $result .= $bar->bar;
                        }

                        return $result;
                    },
                ],
                [new CallbacksObject('baz'), new CallbacksObject('quux')],
                new CallbacksObject('bazquux'),
            ],
            'Count a property' => [
                [
                    'bar' => fn (array $bars) => \count($bars),
                ],
                [new CallbacksObject(), new CallbacksObject()],
                new CallbacksObject(2),
            ],
        ];
    }

    public function providerDenormalizeCallbacksWithTypedProperty(): array
    {
        return [
            'Change a typed string' => [
                [
                    'foo' => function ($foo) {
                        $this->assertEquals('foo', $foo);

                        return $foo;
                    },
                ],
                'foo',
                new CallbacksObject(null, 'foo'),
            ],
            'Null an typed item' => [
                [
                    'foo' => function ($value, $object, $attributeName, $format, $context) {
                        $this->assertSame('fool', $value);
                        $this->assertTrue(is_a($object, CallbacksObject::class, true));
                        $this->assertSame('foo', $attributeName);
                        $this->assertSame('any', $format);
                        $this->assertIsArray($context);
                    },
                ],
                'fool',
                new CallbacksObject(null, null),
            ],
        ];
    }

    public function provideInvalidCallbacks()
    {
        return [
            [['bar' => null]],
            [['bar' => 'thisisnotavalidfunction']],
        ];
    }

    protected function getCallbackPropertyTypeExtractor(): PropertyInfoExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        return new PropertyInfoExtractor(
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor]
        );
    }
}
