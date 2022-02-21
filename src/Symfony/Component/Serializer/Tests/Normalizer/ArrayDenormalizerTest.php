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
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;

class ArrayDenormalizerTest extends TestCase
{
    /**
     * @var ArrayDenormalizer
     */
    private $denormalizer;

    /**
     * @var MockObject&ContextAwareDenormalizerInterface
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(ContextAwareDenormalizerInterface::class);
        $this->denormalizer = new ArrayDenormalizer();
        $this->denormalizer->setDenormalizer($this->serializer);
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

    public function testSupportsOtherDatatype()
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization(
                '83fd8e7c-61d4-4318-af88-fb34bd05e31f',
                __NAMESPACE__.'\Uuid'
            )
        );

        $denormalizer2 = new ArrayDenormalizer(true);
        $denormalizer2->setDenormalizer($this->serializer);

        $this->assertFalse(
            $denormalizer2->supportsDenormalization(
                '83fd8e7c-61d4-4318-af88-fb34bd05e31f',
                __NAMESPACE__.'\Uuid'
            )
        );
    }

    public function testSupportsValidFirstArrayElement()
    {
        $denormalizer = new ArrayDenormalizer(true);
        $denormalizer->setDenormalizer($this->serializer);

        $this->serializer->expects($this->once())
            ->method('supportsDenormalization')
            ->with(['foo' => 'one', 'bar' => 'two'], ArrayDummy::class, 'json', [])
            ->willReturn(true);

        $this->assertTrue(
            $denormalizer->supportsDenormalization(
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                __NAMESPACE__.'\ArrayDummy[]',
                'json'
            )
        );
    }

    public function testSupportsInValidFirstArrayElement()
    {
        $denormalizer = new ArrayDenormalizer(true);
        $denormalizer->setDenormalizer($this->serializer);

        $this->serializer->expects($this->once())
            ->method('supportsDenormalization')
            ->with(['foo' => 'one', 'bar' => 'two'], ArrayDummy::class, 'json', [])
            ->willReturn(false);

        $this->assertFalse(
            $denormalizer->supportsDenormalization(
                [
                    ['foo' => 'one', 'bar' => 'two'],
                    ['foo' => 'three', 'bar' => 'four'],
                ],
                __NAMESPACE__.'\ArrayDummy[]',
                'json'
            )
        );
    }

    public function testSupportsNoFirstArrayElement()
    {
        $denormalizer = new ArrayDenormalizer(true);
        $denormalizer->setDenormalizer($this->serializer);

        $this->serializer->expects($this->once())
            ->method('supportsDenormalization')
            ->with($this->isNull(), ArrayDummy::class, 'json', [])
            ->willReturn(true);

        $this->assertTrue(
            $denormalizer->supportsDenormalization(
                [],
                __NAMESPACE__.'\ArrayDummy[]',
                'json'
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
