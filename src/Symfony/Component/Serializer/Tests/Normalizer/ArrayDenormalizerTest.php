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
use Symfony\Component\Serializer\SerializerInterface;

class ArrayDenormalizerTest extends TestCase
{
    /**
     * @var ArrayDenormalizer
     */
    private $denormalizer;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder('Symfony\Component\Serializer\Serializer')->getMock();
        $this->denormalizer = new ArrayDenormalizer();
        $this->denormalizer->setSerializer($this->serializer);
    }

    public function testDenormalize()
    {
        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->withConsecutive(
                [['foo' => 'one', 'bar' => 'two']],
                [['foo' => 'three', 'bar' => 'four']]
            )
            ->willReturnOnConsecutiveCalls(
                new ArrayDummy('one', 'two'),
                new ArrayDummy('three', 'four')
            );

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
            ->with($this->anything(), ArrayDummy::class, $this->anything())
            ->willReturn(true);

        $this->assertTrue(
            $this->denormalizer->supportsDenormalization(
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                __NAMESPACE__.'\ArrayDummy[]'
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
