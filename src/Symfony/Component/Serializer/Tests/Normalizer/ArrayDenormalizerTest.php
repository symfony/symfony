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
