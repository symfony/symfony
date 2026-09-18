<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntity;

class ReflectionPropertyNameCollectionFactoryTest extends TestCase
{
    public function testCollectsOwnPropertiesThenPrivatePropertiesOfParentClasses()
    {
        $factory = new ReflectionPropertyNameCollectionFactory();

        $names = $factory->create(new ChildEntity(1, 'foo'));

        $this->assertSame(['name', 'id'], $names);
        $this->assertCount(2, $names);
    }

    public function testExcludesStaticProperties()
    {
        $factory = new ReflectionPropertyNameCollectionFactory();
        $object = new class {
            public static int $counter = 0;
            public string $name = 'foo';
        };

        $this->assertSame(['name'], $factory->create($object));
    }

    public function testStdClassHasNoDeclaredProperties()
    {
        $factory = new ReflectionPropertyNameCollectionFactory();
        $object = new \stdClass();
        $object->dynamic = 'foo';

        $this->assertCount(0, $factory->create($object));
    }

    public function testCollectionIsCachedPerClass()
    {
        $factory = new ReflectionPropertyNameCollectionFactory();

        $this->assertSame($factory->create(new ChildEntity(1, 'foo')), $factory->create(new ChildEntity(2, 'bar')));
    }
}
