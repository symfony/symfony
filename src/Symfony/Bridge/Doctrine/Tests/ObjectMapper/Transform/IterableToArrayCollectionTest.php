<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ObjectMapper\Transform;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ObjectMapper\Transform\IterableToArrayCollection;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassA;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassB;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassC;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

class IterableToArrayCollectionTest extends TestCase
{
    public function testMapCollectionWithTargetClass()
    {
        if (!class_exists(ObjectMapper::class)) {
            self::markTestSkipped('The ObjectMapper class is not available.');
        }

        $source = new ClassB(
            collection: new ArrayCollection([new ClassA('a'), new ClassA('b')]),
        );

        $mapper = new ObjectMapper();
        $target = $mapper->map($source, ClassC::class);

        $this->assertInstanceOf(ClassC::class, $target);
        $this->assertInstanceOf(ArrayCollection::class, $target->collection);
        $this->assertCount(2, $target->collection);
        $this->assertEquals('a', $target->collection[0]->value);
        $this->assertEquals('b', $target->collection[1]->value);
    }

    public function testElementsAreMappedByTheOwningMapper()
    {
        if (!class_exists(ObjectMapper::class)) {
            self::markTestSkipped('The ObjectMapper class is not available.');
        }

        $mapper = new class(new ObjectMapper()) implements ObjectMapperInterface {
            public array $sources = [];

            public function __construct(private ObjectMapperInterface $mapper)
            {
                $this->mapper = $mapper->withObjectMapper($this);
            }

            public function map(object $source, object|string|null $target = null): object
            {
                $this->sources[] = $source;

                return $this->mapper->map($source, $target);
            }
        };

        $a = new ClassA('a');
        $b = new ClassA('b');
        $source = new ClassB(new ArrayCollection([$a, $b]));
        $mapper->map($source, ClassC::class);

        $this->assertSame([$source, $a, $b], $mapper->sources);
    }

    public function testWithObjectMapperLeavesTheOriginalInstanceUntouched()
    {
        if (!class_exists(ObjectMapper::class)) {
            self::markTestSkipped('The ObjectMapper class is not available.');
        }

        $mapper = new class implements ObjectMapperInterface {
            public function map(object $source, object|string|null $target = null): object
            {
                throw new \LogicException('This mapper should not be used.');
            }
        };

        $transform = new IterableToArrayCollection(new MapCollection(null, ClassA::class));
        $this->assertNotSame($transform, $transform->withObjectMapper($mapper));

        $collection = $transform([new ClassA('a')], new ClassB(), null);
        $this->assertSame('a', $collection[0]->value);
    }
}
