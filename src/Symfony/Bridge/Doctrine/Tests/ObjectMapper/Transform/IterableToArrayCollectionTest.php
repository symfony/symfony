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
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassA;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassB;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ObjectMapper\IterableToArrayCollection\ClassC;
use Symfony\Component\ObjectMapper\ObjectMapper;

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
}
