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
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\PropertyReadability;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestWithSourceAndAutoMappedView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectionlessTargetTransform\Product;
use Symfony\Component\ObjectMapper\Tests\Fixtures\DirectionlessTargetTransform\ProductInput;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\Source as ExplicitSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource\Target as ExplicitTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUser;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MagicGet\MagicGetUserView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntityDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\Lead;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\LeadDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\ToTypeDto;
use Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata\TypeDto;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ReflectionPropertyMetadataFactoryTest extends TestCase
{
    public function testResolvesExplicitSourceNameOfAnInboundMappingReadFromTheTarget()
    {
        $factory = $this->createFactory();
        $target = (new \ReflectionClass(ExplicitTarget::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new ExplicitSource(), $target, 'reason');

        $this->assertCount(1, $metadata['mappings']);
        $this->assertSame('reasonText', $metadata['mappings'][0]['sourceProperty']);
        $this->assertSame('reason', $metadata['mappings'][0]['targetProperty']);
        $this->assertSame(PropertyReadability::INITIALIZED, $metadata['mappings'][0]['sourceReadability']);
        $this->assertTrue($metadata['mappings'][0]['targetWritable']);
        $this->assertNull($metadata['sameNameMapping']);
    }

    public function testAutoMappedPropertyReadFromTheTargetHasNoMappingAndNoSameNameMapping()
    {
        $factory = $this->createFactory();

        $metadata = $factory->create(new Cost(1, 2), new CostRequestWithSourceAndAutoMappedView(), 'amount');

        $this->assertSame([], $metadata['mappings']);
        $this->assertTrue($metadata['targetHasProperty']);
        $this->assertNull($metadata['sameNameMapping']);
        $this->assertSame(PropertyReadability::INITIALIZED, $metadata['readability']);
        $this->assertTrue($metadata['targetWritable']);
    }

    public function testSameNameMappingIsTakenFromTheTargetPropertyWhenTheSourceCarriesMetadata()
    {
        $factory = $this->createFactory();
        $target = (new \ReflectionClass(LeadDto::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new Lead(), $target, 'type');

        $this->assertSame([], $metadata['mappings']);
        $this->assertNotNull($metadata['sameNameMapping']);
        $this->assertSame([ToTypeDto::class, 'transform'], $metadata['sameNameMapping']->transform);
        $this->assertSame(TypeDto::class, $metadata['targetPropertyClass']);
    }

    public function testTargetMappingWithoutSourceIsNotUsedForTheSameNameCopy()
    {
        $factory = $this->createFactory();

        $metadata = $factory->create(new ProductInput(), new Product(), 'reference');

        $this->assertSame([], $metadata['mappings']);
        $this->assertNull($metadata['sameNameMapping']);
    }

    public function testMappingsDeclaredForAnotherTargetClassAreFiltered()
    {
        $metadataFactory = new class implements ObjectMapperMetadataFactoryInterface {
            public function create(object $object, ?string $property = null, array $context = []): array
            {
                if (null === $property) {
                    return [new Mapping(target: ChildEntityDto::class)];
                }

                return [
                    new Mapping(target: 'kept', targetClass: ChildEntityDto::class),
                    new Mapping(target: 'dropped', targetClass: \stdClass::class),
                ];
            }
        };
        $factory = new ReflectionPropertyMetadataFactory($metadataFactory, new ReflectionClassMetadataFactory($metadataFactory));
        $target = (new \ReflectionClass(ChildEntityDto::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new ChildEntity(1, 'foo'), $target, 'name');

        $this->assertCount(1, $metadata['mappings']);
        $this->assertSame('kept', $metadata['mappings'][0]['targetProperty']);
    }

    public function testPrivateParentPropertyWithoutMagicGetIsNeverReadable()
    {
        $factory = $this->createFactory();
        $target = (new \ReflectionClass(ChildEntityDto::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new ChildEntity(1, 'foo'), $target, 'id');

        $this->assertSame(PropertyReadability::NEVER, $metadata['readability']);
        $this->assertTrue($metadata['sourceDeclared']);
    }

    public function testPrivatePropertyWithMagicGetIsAlwaysReadable()
    {
        $factory = $this->createFactory();

        $metadata = $factory->create(new MagicGetUser(), new MagicGetUserView(), 'name');

        $this->assertSame(PropertyReadability::ALWAYS, $metadata['readability']);
    }

    public function testUndeclaredPropertyIsDynamic()
    {
        $factory = $this->createFactory();

        $metadata = $factory->create(new \stdClass(), new MagicGetUserView(), 'name');

        $this->assertSame(PropertyReadability::DYNAMIC, $metadata['readability']);
        $this->assertFalse($metadata['sourceDeclared']);
    }

    public function testReadabilityIsDelegatedToThePropertyAccessorWhenConfigured()
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();
        $factory = new ReflectionPropertyMetadataFactory($metadataFactory, new ReflectionClassMetadataFactory($metadataFactory), PropertyAccess::createPropertyAccessor());

        $metadata = $factory->create(new MagicGetUser(), new MagicGetUserView(), 'id');

        $this->assertSame(PropertyReadability::ACCESSOR, $metadata['readability']);
    }

    public function testMetadataIsCachedPerSourceAndTargetClass()
    {
        $factory = $this->createFactory();

        $this->assertSame(
            $factory->create(new Cost(1, 2), new CostRequestWithSourceAndAutoMappedView(), 'amount'),
            $factory->create(new Cost(3, 4), new CostRequestWithSourceAndAutoMappedView(), 'amount'),
        );
    }

    private function createFactory(): ReflectionPropertyMetadataFactory
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();

        return new ReflectionPropertyMetadataFactory($metadataFactory, new ReflectionClassMetadataFactory($metadataFactory));
    }
}
