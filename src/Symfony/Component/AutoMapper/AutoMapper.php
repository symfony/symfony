<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

use Doctrine\Common\Annotations\AnnotationReader;
use PhpParser\ParserFactory;
use Symfony\Component\AutoMapper\Exception\NoMappingFoundException;
use Symfony\Component\AutoMapper\Extractor\FromSourceMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\FromTargetMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\PrivateReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\SourceTargetMappingExtractor;
use Symfony\Component\AutoMapper\Generator\Generator;
use Symfony\Component\AutoMapper\Loader\ClassLoaderInterface;
use Symfony\Component\AutoMapper\Loader\EvalLoader;
use Symfony\Component\AutoMapper\Transformer\ArrayTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\ChainTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\DateTimeTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\MultipleTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\NullableTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\ObjectTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\UniqueTypeTransformerFactory;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;

/**
 * Maps a source data structure (object or array) to a target one.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class AutoMapper implements AutoMapperInterface, AutoMapperRegistryInterface, MapperGeneratorMetadataRegistryInterface
{
    /** @var MapperGeneratorMetadataInterface[] */
    private $metadata = [];

    /** @var GeneratedMapper[] */
    private $mapperRegistry = [];

    private $classLoader;

    private $mapperConfigurationFactory;

    public function __construct(ClassLoaderInterface $classLoader, MapperGeneratorMetadataFactoryInterface $mapperConfigurationFactory = null)
    {
        $this->classLoader = $classLoader;
        $this->mapperConfigurationFactory = $mapperConfigurationFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function register(MapperGeneratorMetadataInterface $metadata): void
    {
        $this->metadata[$metadata->getSource()][$metadata->getTarget()] = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapper(string $source, string $target): MapperInterface
    {
        $metadata = $this->getMetadata($source, $target);

        if (null === $metadata) {
            throw new NoMappingFoundException('No mapping found for source '.$source.' and target '.$target);
        }

        $className = $metadata->getMapperClassName();

        if (\array_key_exists($className, $this->mapperRegistry)) {
            return $this->mapperRegistry[$className];
        }

        if (!class_exists($className)) {
            $this->classLoader->loadClass($metadata);
        }

        $this->mapperRegistry[$className] = new $className();
        $this->mapperRegistry[$className]->injectMappers($this);

        foreach ($metadata->getCallbacks() as $property => $callback) {
            $this->mapperRegistry[$className]->addCallback($property, $callback);
        }

        return $this->mapperRegistry[$className];
    }

    /**
     * {@inheritdoc}
     */
    public function hasMapper(string $source, string $target): bool
    {
        return null !== $this->getMetadata($source, $target);
    }

    /**
     * {@inheritdoc}
     */
    public function map($sourceData, $targetData, array $context = [])
    {
        $source = null;
        $target = null;

        if (null === $sourceData) {
            return null;
        }

        if (\is_object($sourceData)) {
            $source = \get_class($sourceData);
        }

        if (\is_array($sourceData)) {
            $source = 'array';
        }

        if (null === $source) {
            throw new NoMappingFoundException('Cannot map this value, source is neither an object or an array.');
        }

        if (\is_object($targetData)) {
            $target = \get_class($targetData);
            $context[MapperContext::TARGET_TO_POPULATE] = $targetData;
        }

        if (\is_array($targetData)) {
            $target = 'array';
            $context[MapperContext::TARGET_TO_POPULATE] = $targetData;
        }

        if (\is_string($targetData)) {
            $target = $targetData;
        }

        if (null === $target) {
            throw new NoMappingFoundException('Cannot map this value, target is neither an object or an array.');
        }

        if ('array' === $source && 'array' === $target) {
            throw new NoMappingFoundException('Cannot map this value, both source and target are array.');
        }

        return $this->getMapper($source, $target)->map($sourceData, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(string $source, string $target): ?MapperGeneratorMetadataInterface
    {
        if (!isset($this->metadata[$source][$target])) {
            if (null === $this->mapperConfigurationFactory) {
                return null;
            }

            $this->register($this->mapperConfigurationFactory->create($this, $source, $target));
        }

        return $this->metadata[$source][$target];
    }

    /**
     * Create an automapper.
     */
    public static function create(
        bool $private = true,
        ClassLoaderInterface $loader = null,
        AdvancedNameConverterInterface $nameConverter = null,
        string $classPrefix = 'Mapper_',
        bool $attributeChecking = true,
        bool $autoRegister = true
    ): self {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        if (null === $loader) {
            $loader = new EvalLoader(new Generator(
                (new ParserFactory())->create(ParserFactory::PREFER_PHP7),
                new ClassDiscriminatorFromClassMetadata($classMetadataFactory)
            ));
        }

        $reflectionExtractor = $private ? new PrivateReflectionExtractor() : new ReflectionExtractor();

        $phpDocExtractor = new PhpDocExtractor();
        $propertyInfoExtractor = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );

        $accessorExtractor = new \Symfony\Component\AutoMapper\Extractor\ReflectionExtractor($private);
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
            $classMetadataFactory,
            $nameConverter
        );

        $fromSourceMappingExtractor = new FromSourceMappingExtractor(
            $propertyInfoExtractor,
            $accessorExtractor,
            $transformerFactory,
            $classMetadataFactory,
            $nameConverter
        );

        $autoMapper = $autoRegister ? new self($loader, new MapperGeneratorMetadataFactory(
            $sourceTargetMappingExtractor,
            $fromSourceMappingExtractor,
            $fromTargetMappingExtractor,
            $classPrefix,
            $attributeChecking
        )) : new self($loader);

        $transformerFactory->addTransformerFactory(new MultipleTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new NullableTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new UniqueTypeTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new DateTimeTransformerFactory());
        $transformerFactory->addTransformerFactory(new BuiltinTransformerFactory());
        $transformerFactory->addTransformerFactory(new ArrayTransformerFactory($transformerFactory));
        $transformerFactory->addTransformerFactory(new ObjectTransformerFactory($autoMapper));

        return $autoMapper;
    }
}
