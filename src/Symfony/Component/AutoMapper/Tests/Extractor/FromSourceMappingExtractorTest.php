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
use Symfony\Component\AutoMapper\Extractor\FromSourceMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\PrivateReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Extractor\ReflectionExtractor;
use Symfony\Component\AutoMapper\MapperMetadata;
use Symfony\Component\AutoMapper\Tests\AutoMapperBaseTest;
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
use Symfony\Component\AutoMapper\Tests\Fixtures;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class FromSourceMappingExtractorTest extends AutoMapperBaseTest
{
    /** @var FromSourceMappingExtractor */
    protected $fromSourceMappingExtractor;

    public function setUp(): void
    {
        parent::setUp();
        $this->fromSourceMappingExtractorBootstrap();
    }

    private function fromSourceMappingExtractorBootstrap(bool $private = true): void
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

        $this->fromSourceMappingExtractor = new FromSourceMappingExtractor(
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

    public function testWithTargetAsArray(): void
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, Fixtures\User::class, 'array');
        $sourcePropertiesMapping = $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(\count($userReflection->getProperties()), $sourcePropertiesMapping);
        /** @var PropertyMapping $propertyMapping */
        foreach ($sourcePropertiesMapping as $propertyMapping) {
            self::assertTrue($userReflection->hasProperty($propertyMapping->getProperty()));
        }
    }

    public function testWithTargetAsStdClass(): void
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, Fixtures\User::class, 'stdClass');
        $sourcePropertiesMapping = $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(\count($userReflection->getProperties()), $sourcePropertiesMapping);
        /** @var PropertyMapping $propertyMapping */
        foreach ($sourcePropertiesMapping as $propertyMapping) {
            self::assertTrue($userReflection->hasProperty($propertyMapping->getProperty()));
        }
    }

    public function testWithSourceAsEmpty(): void
    {
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, Fixtures\Empty_::class, 'array');
        $sourcePropertiesMapping = $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);

        self::assertCount(0, $sourcePropertiesMapping);
    }

    public function testWithSourceAsPrivate(): void
    {
        $privateReflection = new \ReflectionClass(Fixtures\Private_::class);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, Fixtures\Private_::class, 'array');
        $sourcePropertiesMapping = $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);
        self::assertCount(\count($privateReflection->getProperties()), $sourcePropertiesMapping);

        $this->fromSourceMappingExtractorBootstrap(false);
        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, Fixtures\Private_::class, 'array');
        $sourcePropertiesMapping = $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);
        self::assertCount(0, $sourcePropertiesMapping);
    }

    public function testWithSourceAsArray(): void
    {
        self::expectException(InvalidMappingException::class);
        self::expectExceptionMessage('Only array or stdClass are accepted as a target');

        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, 'array', Fixtures\User::class);
        $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);
    }

    public function testWithSourceAsStdClass(): void
    {
        self::expectException(InvalidMappingException::class);
        self::expectExceptionMessage('Only array or stdClass are accepted as a target');

        $mapperMetadata = new MapperMetadata($this->autoMapper, $this->fromSourceMappingExtractor, 'stdClass', Fixtures\User::class);
        $this->fromSourceMappingExtractor->getPropertiesMapping($mapperMetadata);
    }
}
