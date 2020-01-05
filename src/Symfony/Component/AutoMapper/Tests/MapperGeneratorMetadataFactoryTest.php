<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\AutoMapper\Extractor\FromSourceMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\FromTargetMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\PrivateReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Extractor\ReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\SourceTargetMappingExtractor;
use Symfony\Component\AutoMapper\MapperGeneratorMetadataFactory;
use Symfony\Component\AutoMapper\MapperGeneratorMetadataFactoryInterface;
use Symfony\Component\AutoMapper\Transformer\ArrayTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\ChainTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\DateTimeTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\MultipleTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\NullableTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\ObjectTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\UniqueTypeTransformerFactory;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class MapperGeneratorMetadataFactoryTest extends AutoMapperBaseTest
{
    /** @var MapperGeneratorMetadataFactoryInterface */
    protected $factory;

    public function setUp(): void
    {
        parent::setUp();

        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $reflectionExtractor = new PrivateReflectionExtractor();

        $phpDocExtractor = new PhpDocExtractor();
        $propertyInfoExtractor = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );

        $accessorExtractor = new ReflectionExtractor(true);
        $transformerFactory = new ChainTransformerFactory();
        $sourceTargetMappingExtractor = new SourceTargetMappingExtractor(
            $propertyInfoExtractor,
            $accessorExtractor,
            $transformerFactory,
            $classMetadataFactory
        );

        $fromTargetMappingExtractor = new FromTargetMappingExtractor(
            $propertyInfoExtractor,
            $accessorExtractor,
            $transformerFactory,
            $classMetadataFactory
        );

        $fromSourceMappingExtractor = new FromSourceMappingExtractor(
            $propertyInfoExtractor,
            $accessorExtractor,
            $transformerFactory,
            $classMetadataFactory
        );

        $this->factory = new MapperGeneratorMetadataFactory(
            $sourceTargetMappingExtractor,
            $fromSourceMappingExtractor,
            $fromTargetMappingExtractor
        );

        $transformerFactory->addTransformerFactory(new MultipleTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new NullableTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new UniqueTypeTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new DateTimeTransformerFactory());
        $transformerFactory->addTransformerFactory(new BuiltinTransformerFactory());
        $transformerFactory->addTransformerFactory(new ArrayTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new ObjectTransformerFactory($this->autoMapper));
    }

    public function testCreateObjectToArray()
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);

        $metadata = $this->factory->create($this->autoMapper, Fixtures\User::class, 'array');
        self::assertFalse($metadata->hasConstructor());
        self::assertTrue($metadata->shouldCheckAttributes());
        self::assertFalse($metadata->isTargetCloneable());
        self::assertEquals(Fixtures\User::class, $metadata->getSource());
        self::assertEquals('array', $metadata->getTarget());
        self::assertCount(\count($userReflection->getProperties()), $metadata->getPropertiesMapping());
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('id'));
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('name'));
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('email'));
    }

    public function testCreateArrayToObject()
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);

        $metadata = $this->factory->create($this->autoMapper, 'array', Fixtures\User::class);
        self::assertTrue($metadata->hasConstructor());
        self::assertTrue($metadata->shouldCheckAttributes());
        self::assertTrue($metadata->isTargetCloneable());
        self::assertEquals('array', $metadata->getSource());
        self::assertEquals(Fixtures\User::class, $metadata->getTarget());
        self::assertCount(\count($userReflection->getProperties()), $metadata->getPropertiesMapping());
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('id'));
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('name'));
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('email'));
    }

    public function testCreateWithBothObjects()
    {
        $userConstructorDTOReflection = new \ReflectionClass(Fixtures\UserConstructorDTO::class);

        $metadata = $this->factory->create($this->autoMapper, Fixtures\UserConstructorDTO::class, Fixtures\User::class);
        self::assertTrue($metadata->hasConstructor());
        self::assertTrue($metadata->shouldCheckAttributes());
        self::assertTrue($metadata->isTargetCloneable());
        self::assertEquals(Fixtures\UserConstructorDTO::class, $metadata->getSource());
        self::assertEquals(Fixtures\User::class, $metadata->getTarget());
        self::assertCount(\count($userConstructorDTOReflection->getProperties()), $metadata->getPropertiesMapping());
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('id'));
        self::assertInstanceOf(PropertyMapping::class, $metadata->getPropertyMapping('name'));
        self::assertNull($metadata->getPropertyMapping('email'));
    }
}
