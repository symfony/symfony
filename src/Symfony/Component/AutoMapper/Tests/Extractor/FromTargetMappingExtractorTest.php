<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Extractor;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\AutoMapper\Exception\InvalidMappingException;
use Symfony\Component\AutoMapper\Extractor\FromTargetMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\PrivateReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Extractor\ReflectionExtractor;
use Symfony\Component\AutoMapper\MapperMetadata;
use Symfony\Component\AutoMapper\Tests\AutoMapperBaseTest;
use Symfony\Component\AutoMapper\Tests\Fixtures;
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
class FromTargetMappingExtractorTest extends AutoMapperBaseTest
{
    /** @var FromTargetMappingExtractor */
    protected $fromTargetMappingExtractor;

    public function setUp(): void
    {
        parent::setUp();
        $this->fromTargetMappingExtractorBootstrap();
    }

    private function fromTargetMappingExtractorBootstrap(bool $private = true): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $reflectionExtractor = $private ? new PrivateReflectionExtractor() : new \Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor();

        $phpDocExtractor = new PhpDocExtractor();
        $propertyInfoExtractor = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );

        $accessorExtractor = new ReflectionExtractor($private);
        $transformerFactory = new ChainTransformerFactory();

        $this->fromTargetMappingExtractor = new FromTargetMappingExtractor(
            $propertyInfoExtractor,
            $accessorExtractor,
            $transformerFactory,
            $classMetadataFactory
        );

        $transformerFactory->addTransformerFactory(new MultipleTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new NullableTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new UniqueTypeTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new DateTimeTransformerFactory());
        $transformerFactory->addTransformerFactory(new BuiltinTransformerFactory());
        $transformerFactory->addTransformerFactory(new ArrayTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new ObjectTransformerFactory($this->autoMapper));
    }

    public function testWithSourceAsArray(): void
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, 'array', Fixtures\User::class);
        $targetPropertiesMapping = $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(\count($userReflection->getProperties()), $targetPropertiesMapping);
        /** @var PropertyMapping $propertyMapping */
        foreach ($targetPropertiesMapping as $propertyMapping) {
            self::assertTrue($userReflection->hasProperty($propertyMapping->getProperty()));
        }
    }

    public function testWithSourceAsStdClass(): void
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, 'stdClass', Fixtures\User::class);
        $targetPropertiesMapping = $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(\count($userReflection->getProperties()), $targetPropertiesMapping);
        /** @var PropertyMapping $propertyMapping */
        foreach ($targetPropertiesMapping as $propertyMapping) {
            self::assertTrue($userReflection->hasProperty($propertyMapping->getProperty()));
        }
    }

    public function testWithTargetAsEmpty(): void
    {
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, 'array', Fixtures\Empty_::class);
        $targetPropertiesMapping = $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(0, $targetPropertiesMapping);
    }

    public function testWithTargetAsPrivate(): void
    {
        $privateReflection = new \ReflectionClass(Fixtures\Private_::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, 'array', Fixtures\Private_::class);
        $targetPropertiesMapping = $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);
        self::assertCount(\count($privateReflection->getProperties()), $targetPropertiesMapping);

        $this->fromTargetMappingExtractorBootstrap(false);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, 'array', Fixtures\Private_::class);
        $targetPropertiesMapping = $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);
        self::assertCount(0, $targetPropertiesMapping);
    }

    public function testWithTargetAsArray(): void
    {
        self::expectException(InvalidMappingException::class);
        self::expectExceptionMessage('Only array or stdClass are accepted as a source');

        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, Fixtures\User::class, 'array');
        $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);
    }

    public function testWithTargetAsStdClass(): void
    {
        self::expectException(InvalidMappingException::class);
        self::expectExceptionMessage('Only array or stdClass are accepted as a source');

        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromTargetMappingExtractor, Fixtures\User::class, 'stdClass');
        $this->fromTargetMappingExtractor->getPropertiesMapping($mapperMetadata);
    }
}
