<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::CALLBACKS.
 */
trait CallbacksTestTrait
{
    abstract protected function getNormalizerForCallbacks(): NormalizerInterface;

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = new CallbacksObject();
        $obj->bar = $valueBar;

        $this->assertEquals(
            $result,
            $normalizer->normalize($obj, 'any', ['callbacks' => $callbacks])
        );
    }

    /**
     * @dataProvider provideInvalidCallbacks()
     */
    public function testUncallableCallbacks($callbacks)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = new CallbacksObject();

        $this->markTestSkipped('Callback validation for callbacks in the context has been forgotten. See https://github.com/symfony/symfony/pull/30950');
        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($obj, null, ['callbacks' => $callbacks]);
    }

    public function provideCallbacks()
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
                ['bar' => 'baz'],
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
                ['bar' => null],
            ],
            'Format a date' => [
                [
                    'bar' => function ($bar) {
                        $this->assertInstanceOf(\DateTime::class, $bar);

                        return $bar->format('d-m-Y H:i:s');
                    },
                ],
                new \DateTime('2011-09-10 06:30:00'),
                ['bar' => '10-09-2011 06:30:00'],
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
                ['bar' => 'bazquux'],
            ],
            'Count a property' => [
                [
                    'bar' => function (array $bars) {
                        return \count($bars);
                    },
                ],
                [new CallbacksObject(), new CallbacksObject()],
                ['bar' => 2],
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
}
