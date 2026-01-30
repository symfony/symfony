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
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ArrayDenormalizerTest extends TestCase
{
    public function testDenormalize(): void
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

    public function testSupportsValidArray(): void
    {
        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects($this->once())
            ->method('supportsDenormalization')
            ->with($this->anything(), ArrayDummy::class, 'json', ['con' => 'text'])
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

    public function testSupportsInvalidArray(): void
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

    public function testSupportsNoArray(): void
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

    public function testDenormalizeWithoutDenormalizer(): void
    {
        $arrayDenormalizer = new ArrayDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayDenormalizer->denormalize([], 'string[]');
    }

    public function testSupportsDenormalizationWithoutDenormalizer(): void
    {
        $arrayDenormalizer = new ArrayDenormalizer();

        $this->expectException(\BadMethodCallException::class);
        $arrayDenormalizer->supportsDenormalization([], 'string[]');
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
