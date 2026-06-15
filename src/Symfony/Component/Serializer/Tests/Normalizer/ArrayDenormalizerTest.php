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
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\UpcomingDenormalizerInterface as DenormalizerInterface;

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

    public function testDenormalize()
    {
        $series = [
            [[['foo' => 'one', 'bar' => 'two']], new ArrayDummy('one', 'two')],
            [[['foo' => 'three', 'bar' => 'four']], new ArrayDummy('three', 'four')],
        ];

        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(function ($data) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, [$data]);

                return $return;
            })
        ;

        $result = $this->denormalizer->denormalize(
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

    public function testSupportsValidArray()
    {
        $expectedCalls = [
            [['foo' => 'one', 'bar' => 'two'], ArrayDummy::class, 'json', ['con' => 'text']],
            [['foo' => 'three', 'bar' => 'four'], ArrayDummy::class, 'json', ['con' => 'text']],
        ];

        $this->serializer->expects($this->exactly(2))
            ->method('supportsDenormalization')
            ->willReturnCallback(function ($data, $type, $format, $context) use (&$expectedCalls) {
                $expected = array_shift($expectedCalls);
                $this->assertSame($expected[0], $data);
                $this->assertSame($expected[1], $type);
                $this->assertSame($expected[2], $format);
                $this->assertSame($expected[3], $context);

                return true;
            });

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

        // Verify all expected calls were made
        $this->assertEmpty($expectedCalls, 'Not all expected calls were made');
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

    public function testPassesCorrectDataToInnerDenormalizer()
    {
        $this->serializer->expects($this->any())
            ->method('supportsDenormalization')
            ->willReturnCallback(function (mixed $data, string $type): bool {
                return ArrayDummy::class === $type && isset($data['foo']) && isset($data['bar']);
            });

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
