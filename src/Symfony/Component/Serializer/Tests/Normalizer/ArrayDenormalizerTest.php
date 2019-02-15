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
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class ArrayDenormalizerTest extends TestCase
{
    /**
     * @var ArrayDenormalizer
     */
    private $denormalizer;

    /**
     * @var SerializerInterface|\PHPUnit_Framework_MockObject_MockObject
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
        $this->serializer->expects($this->at(0))
            ->method('denormalize')
            ->with(['foo' => 'one', 'bar' => 'two'], __NAMESPACE__.'\ArrayDummy')
            ->will($this->returnValue(new ArrayDummy('one', 'two')));

        $this->serializer->expects($this->at(1))
            ->method('denormalize')
            ->with(['foo' => 'three', 'bar' => 'four'], __NAMESPACE__.'\ArrayDummy')
            ->will($this->returnValue(new ArrayDummy('three', 'four')));

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

    public function testDenormalizeNested()
    {
        $m = $this->getMockBuilder(DenormalizerInterface::class)
            ->getMock();

        $denormalizer = new ArrayDenormalizer();
        $serializer = new Serializer([$denormalizer, $m]);
        $denormalizer->setSerializer($serializer);

        $m->method('denormalize')
            ->with(['foo' => 'one', 'bar' => 'two'], __NAMESPACE__.'\ArrayDummy')
            ->willReturn(new ArrayDummy('one', 'two'));

        $m->method('supportsDenormalization')
            ->with($this->anything(), __NAMESPACE__.'\ArrayDummy')
            ->willReturn(true);

        $result = $denormalizer->denormalize(
            [
                [
                    ['foo' => 'one', 'bar' => 'two'],
                ],
            ],
            __NAMESPACE__.'\ArrayDummy[][]',
            null,
            ['key_types' => [new Type('int'), new Type('int')]]
        );

        $this->assertEquals(
            [[
                new ArrayDummy('one', 'two'),
            ]],
            $result
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\NotNormalizableValueException
     */
    public function testDenormalizeCheckKeyType()
    {
        $this->denormalizer->denormalize(
            [
                ['foo' => 'one', 'bar' => 'two'],
            ],
            __NAMESPACE__.'\ArrayDummy[]',
            null,
            ['key_type' => new Type('string')]
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\NotNormalizableValueException
     */
    public function testDenormalizeCheckKeyTypes()
    {
        $this->denormalizer->denormalize(
            [
                ['foo' => 'one', 'bar' => 'two'],
            ],
            __NAMESPACE__.'\ArrayDummy[]',
            null,
            ['key_types' => [new Type('string')]]
        );
    }

    public function testDenormalizeNestedCheckKeyTypes()
    {
        $m = $this->getMockBuilder(DenormalizerInterface::class)
            ->getMock();

        $denormalizer = new ArrayDenormalizer();
        $serializer = new Serializer([$denormalizer, $m]);
        $denormalizer->setSerializer($serializer);

        $m->method('denormalize')
            ->with(['foo' => 'one', 'bar' => 'two'], __NAMESPACE__.'\ArrayDummy')
            ->willReturn(new ArrayDummy('one', 'two'));

        $m->method('supportsDenormalization')
            ->with($this->anything(), __NAMESPACE__.'\ArrayDummy')
            ->willReturn(true);

        $result = $denormalizer->denormalize(
            [
                'top' => [
                    ['foo' => 'one', 'bar' => 'two'],
                ],
            ],
            __NAMESPACE__.'\ArrayDummy[][]',
            null,
            ['key_types' => [new Type('string')], 'key_type' => new Type('int')]
        );

        $this->assertEquals(
            [
                'top' => [
                    new ArrayDummy('one', 'two'),
                ],
            ],
            $result
        );
    }

    public function testSupportsValidArray()
    {
        $this->serializer->expects($this->once())
            ->method('supportsDenormalization')
            ->with($this->anything(), __NAMESPACE__.'\ArrayDummy', $this->anything())
            ->will($this->returnValue(true));

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
            ->will($this->returnValue(false));

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
                __NAMESPACE__.'\ArrayDummy'
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
