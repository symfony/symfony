<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\CollectionSource;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\CollectionSourceItem;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\CollectionTarget;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\NestedEntity;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\NestedEntityResource;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ObjectMapped;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ObjectToBeMapped;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ParentEntity;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper\ParentEntityResource;
use Symfony\Component\ObjectMapper\Metadata\ReverseClassObjectMapperMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectMapperTest extends AbstractWebTestCase
{
    public function testObjectMapper()
    {
        static::bootKernel(['test_case' => 'ObjectMapper']);

        /** @var Symfony\Component\ObjectMapper\ObjectMapperInterface<ObjectMapped> */
        $objectMapper = static::getContainer()->get('object_mapper.alias');
        $mapped = $objectMapper->map(new ObjectToBeMapped());
        $this->assertSame($mapped->a, 'transformed');
    }

    public function testMapCollectionUsesContainerObjectMapper()
    {
        static::bootKernel(['test_case' => 'ObjectMapper']);

        $objectMapper = static::getContainer()->get('object_mapper.alias');
        $source = new CollectionSource([
            new CollectionSourceItem('foo'),
            new CollectionSourceItem('bar'),
        ]);

        /** @var CollectionTarget $mapped */
        $mapped = $objectMapper->map($source);

        $this->assertCount(2, $mapped->items);
        $this->assertSame('foo', $mapped->items[0]->getName());
        $this->assertSame('bar', $mapped->items[1]->getName());
    }

    public function testMapNestedObjectWhoseClassDeclaresNoMapping()
    {
        if (!class_exists(ReverseClassObjectMapperMetadataFactory::class)) {
            $this->markTestSkipped('Test requires symfony/object-mapper 8.2+');
        }

        static::bootKernel(['test_case' => 'ObjectMapper']);

        $objectMapper = static::getContainer()->get('object_mapper.alias');

        /** @var ParentEntityResource $mapped */
        $mapped = $objectMapper->map(new ParentEntity('Laptop', new NestedEntity('Electronics')), ParentEntityResource::class);

        $this->assertSame('Laptop', $mapped->name);
        $this->assertInstanceOf(NestedEntityResource::class, $mapped->nested);
        $this->assertSame('Electronics', $mapped->nested->name);
    }
}
