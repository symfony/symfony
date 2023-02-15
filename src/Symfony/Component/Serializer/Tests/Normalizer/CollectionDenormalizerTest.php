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
use Symfony\Component\Serializer\Normalizer\CollectionDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CollectionDenormalizerTest extends TestCase
{
    /**
     * @var CollectionDenormalizer
     */
    private $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new CollectionDenormalizer();
        $this->denormalizer->setDenormalizer(new Serializer(
            [
                new CollectionDenormalizer(),
                new ArrayDenormalizer(),
                new ObjectNormalizer(),
            ]
        ));
    }

    public function testDenormalize()
    {
        $result = $this->denormalizer->denormalize(
            [
                ['foo' => 'one', 'bar' => 'two'],
                ['foo' => 'three', 'bar' => 'four'],
            ],
            CollectionDummy::class
        );

        $this->assertInstanceOf(CollectionDummy::class, $result);
        $this->assertEquals(
            [
                new ObjectDummy('one', 'two'),
                new ObjectDummy('three', 'four'),
            ],
            [
                $result[0],
                $result[1],
            ]
        );
    }

    public function testSupportsValidArray()
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([
                ['foo' => 'one', 'bar' => 'two'],
                ['foo' => 'three', 'bar' => 'four'],
            ], CollectionDummy::class)
        );
    }

    public function testSupportsValidCollection()
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([
                ['foo' => 'one', 'bar' => 'two'],
            ], CollectionDummy::class)
        );
    }

    public function testSupportsInvalidCollection()
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([
                ['foo' => 'one', 'bar' => 'two'],
            ], ObjectDummy::class)
        );
    }
}

class CollectionDummy implements \ArrayAccess
{
    private array $collection;

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->collection[$offset]);
    }

    public function offsetGet(mixed $offset): ?ObjectDummy
    {
        return $this->collection[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->collection[] = $value;
        } else {
            $this->collection[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->collection[$offset]);
    }
}

class ObjectDummy
{
    public string $foo;
    public string $bar;

    public function __construct(
        string $foo,
        string $bar
    ) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
