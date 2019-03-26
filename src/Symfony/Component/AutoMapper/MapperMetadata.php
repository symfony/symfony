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

use Symfony\Component\AutoMapper\Extractor\MappingExtractorInterface;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Extractor\ReadAccessor;
use Symfony\Component\AutoMapper\Transformer\CallbackTransformer;
use Symfony\Component\AutoMapper\Transformer\MapperDependency;

/**
 * Mapper metadata.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class MapperMetadata implements MapperGeneratorMetadataInterface
{
    private $mappingExtractor;

    private $customMapping = [];

    private $propertiesMapping;

    private $metadataRegistry;

    private $source;

    private $target;

    private $className;

    private $isConstructorAllowed;

    private $dateTimeFormat;

    private $classPrefix;

    private $attributeChecking;

    public function __construct(MapperGeneratorMetadataRegistryInterface $metadataRegistry, MappingExtractorInterface $mappingExtractor, string $source, string $target, string $classPrefix = 'Mapper_')
    {
        $this->mappingExtractor = $mappingExtractor;
        $this->metadataRegistry = $metadataRegistry;
        $this->source = $source;
        $this->target = $target;
        $this->isConstructorAllowed = true;
        $this->dateTimeFormat = \DateTime::RFC3339;
        $this->classPrefix = $classPrefix;
        $this->attributeChecking = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertiesMapping(): array
    {
        if (null === $this->propertiesMapping) {
            $this->buildPropertyMapping();
        }

        return $this->propertiesMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyMapping(string $property): ?PropertyMapping
    {
        if (null === $this->propertiesMapping) {
            $this->buildPropertyMapping();
        }

        return $this->propertiesMapping[$property] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasConstructor(): bool
    {
        if (!$this->isConstructorAllowed()) {
            return false;
        }

        if (\in_array($this->target, ['array', \stdClass::class], true)) {
            return false;
        }

        $reflection = new \ReflectionClass($this->getTarget());
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return false;
        }

        $parameters = $constructor->getParameters();
        $mandatoryParameters = [];

        foreach ($parameters as $parameter) {
            if (!$parameter->isOptional()) {
                $mandatoryParameters[] = $parameter;
            }
        }

        if (!$mandatoryParameters) {
            return true;
        }

        foreach ($mandatoryParameters as $mandatoryParameter) {
            $readAccessor = $this->mappingExtractor->getReadAccessor($this->source, $this->target, $mandatoryParameter->getName());

            if (null === $readAccessor) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isTargetCloneable(): bool
    {
        $reflection = new \ReflectionClass($this->getTarget());

        return $reflection->isCloneable() && !$reflection->hasMethod('__clone');
    }

    /**
     * {@inheritdoc}
     */
    public function canHaveCircularReference(): bool
    {
        $checked = [];

        return $this->checkCircularMapperConfiguration($this, $checked);
    }

    /**
     * {@inheritdoc}
     */
    public function getMapperClassName(): string
    {
        if (null !== $this->className) {
            return $this->className;
        }

        return $this->className = sprintf('%s%s_%s', $this->classPrefix, str_replace('\\', '_', $this->source), str_replace('\\', '_', $this->target));
    }

    /**
     * {@inheritdoc}
     */
    public function getHash(): string
    {
        $hash = '';

        if (!\in_array($this->source, ['array', \stdClass::class], true)) {
            $reflection = new \ReflectionClass($this->source);
            $hash .= filemtime($reflection->getFileName());
        }

        if (!\in_array($this->target, ['array', \stdClass::class], true)) {
            $reflection = new \ReflectionClass($this->target);
            $hash .= filemtime($reflection->getFileName());
        }

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function isConstructorAllowed(): bool
    {
        return $this->isConstructorAllowed;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTimeFormat(): string
    {
        return $this->dateTimeFormat;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbacks(): array
    {
        return $this->customMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCheckAttributes(): bool
    {
        return $this->attributeChecking;
    }

    /**
     * Set DateTime format to use when generating a mapper.
     */
    public function setDateTimeFormat(string $dateTimeFormat): void
    {
        $this->dateTimeFormat = $dateTimeFormat;
    }

    /**
     * Whether or not the constructor should be used.
     */
    public function setConstructorAllowed(bool $isConstructorAllowed): void
    {
        $this->isConstructorAllowed = $isConstructorAllowed;
    }

    /**
     * Set a callable to use when mapping a specific property.
     */
    public function forMember(string $property, callable $callback): void
    {
        $this->customMapping[$property] = $callback;
    }

    /**
     * Whether or not attribute checking code should be generated.
     */
    public function setAttributeChecking(bool $attributeChecking): void
    {
        $this->attributeChecking = $attributeChecking;
    }

    private function buildPropertyMapping()
    {
        $this->propertiesMapping = [];

        foreach ($this->mappingExtractor->getPropertiesMapping($this) as $propertyMapping) {
            $this->propertiesMapping[$propertyMapping->getProperty()] = $propertyMapping;
        }

        foreach ($this->customMapping as $property => $callback) {
            $this->propertiesMapping[$property] = new PropertyMapping(
                new ReadAccessor(ReadAccessor::TYPE_SOURCE, $property),
                $this->mappingExtractor->getWriteMutator($this->source, $this->target, $property),
                new CallbackTransformer($property),
                $property,
                false
            );
        }
    }

    private function checkCircularMapperConfiguration(MapperGeneratorMetadataInterface $configuration, &$checked)
    {
        foreach ($configuration->getPropertiesMapping() as $propertyMapping) {
            /** @var MapperDependency $dependency */
            foreach ($propertyMapping->getTransformer()->getDependencies() as $dependency) {
                if (isset($checked[$dependency->getName()])) {
                    continue;
                }

                $checked[$dependency->getName()] = true;

                if ($dependency->getSource() === $this->getSource() && $dependency->getTarget() === $this->getTarget()) {
                    return true;
                }

                $subConfiguration = $this->metadataRegistry->getMetadata($dependency->getSource(), $dependency->getTarget());

                if (null !== $subConfiguration && true === $this->checkCircularMapperConfiguration($subConfiguration, $checked)) {
                    return true;
                }
            }
        }

        return false;
    }
}
