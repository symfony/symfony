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
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestWithSourceAndAutoMappedView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument\ConstructorTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument\InputSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\PrivateParentProperty\ChildEntityDto;

class ReflectionClassMetadataFactoryTest extends TestCase
{
    public function testMetadataIsReadFromTheTargetWhenTheSourceCarriesNone()
    {
        $factory = new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory());

        $metadata = $factory->create(new Cost(1, 2), new CostRequestWithSourceAndAutoMappedView());

        $this->assertTrue($metadata['readMetadataFromTarget']);
        $this->assertSame([], $metadata['classMappings']);
        $this->assertCount(1, $metadata['classMappingsFromTarget']);
        $this->assertSame(Cost::class, $metadata['classMappingsFromTarget'][0]->source);
        $this->assertFalse($metadata['hasConstructor']);
        $this->assertSame([], $metadata['constructorParameters']);
        $this->assertSame(0, $metadata['requiredConstructorParameters']);
    }

    public function testMetadataIsReadFromTheSourceWhenItCarriesAClassLevelMap()
    {
        $factory = new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory());
        $target = (new \ReflectionClass(ChildEntityDto::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new ChildEntity(1, 'foo'), $target);

        $this->assertFalse($metadata['readMetadataFromTarget']);
        $this->assertCount(1, $metadata['classMappings']);
        $this->assertSame(ChildEntityDto::class, $metadata['classMappings'][0]->target);
        $this->assertSame([], $metadata['classMappingsFromTarget']);
        $this->assertTrue($metadata['hasConstructor']);
        $this->assertSame(['id', 'name'], array_keys($metadata['constructorParameters']));
        $this->assertTrue($metadata['constructorParameters']['id']['hasDefault']);
        $this->assertNull($metadata['constructorParameters']['id']['default']);
        $this->assertSame(0, $metadata['requiredConstructorParameters']);
    }

    public function testRequiredConstructorParameterWithoutDefault()
    {
        $factory = new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory());
        $target = (new \ReflectionClass(ConstructorTarget::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create(new InputSource(), $target);

        $this->assertFalse($metadata['constructorParameters']['name']['hasDefault']);
        $this->assertSame(1, $metadata['requiredConstructorParameters']);
        $this->assertFalse($metadata['constructorParameters']['name']['readOnly']);
    }

    public function testMetadataIsCachedPerSourceAndTargetClass()
    {
        $factory = new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory());

        $this->assertSame(
            $factory->create(new Cost(1, 2), new CostRequestWithSourceAndAutoMappedView()),
            $factory->create(new Cost(3, 4), new CostRequestWithSourceAndAutoMappedView()),
        );
    }
}
