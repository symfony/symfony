<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Serializer\Attribute\DiscriminatorMapType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Loader for PHP attributes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class AttributeLoader implements LoaderChainAwareInterface
{
    use AccessorCollisionResolverTrait;

    private const KNOWN_ATTRIBUTES = [
        DiscriminatorMap::class,
        DiscriminatorMapType::class,
        Groups::class,
        Ignore::class,
        MaxDepth::class,
        SerializedName::class,
        SerializedPath::class,
        Context::class,
    ];

    private bool $deferDiscriminatorMapValidation = false;

    private array $deferredDiscriminatorMapTypes = [];

    /**
     * @param array<class-string, class-string[]>                   $mappedClasses
     * @param array<class-string, array<string, class-string>>|null $discriminatorMapTypes The registry of classes contributed to discriminator
     *                                                                                     maps, or null when contributions are not wired at all
     */
    public function __construct(
        private bool $allowAnyClass = true,
        private array $mappedClasses = [],
        private ?array $discriminatorMapTypes = null,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function getMappedClasses(): array
    {
        return array_keys($this->mappedClasses + ($this->discriminatorMapTypes ?? []));
    }

    public function prepareLoading(ClassMetadataInterface $metadata): void
    {
        $this->deferDiscriminatorMapValidation = true;
        unset($this->deferredDiscriminatorMapTypes[$metadata->getName()]);
    }

    public function finalizeLoading(ClassMetadataInterface $metadata): void
    {
        $this->deferDiscriminatorMapValidation = false;
        $deferred = $this->deferredDiscriminatorMapTypes[$metadata->getName()] ?? [];
        unset($this->deferredDiscriminatorMapTypes[$metadata->getName()]);

        foreach ($deferred as [$type, $mappedClass]) {
            $this->addDiscriminatorMapType($type, $mappedClass, $metadata);
        }

        $this->validateDiscriminatorMap($metadata);
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $className = $classMetadata->getName();

        $sourceClasses = $this->mappedClasses[$className] ??= $this->allowAnyClass ? [$className] : [];
        $discriminatorMapTypes = $this->discriminatorMapTypes[$className] ?? [];

        if (!$sourceClasses && !$discriminatorMapTypes) {
            return false;
        }

        // When a class is the target of #[ExtendsSerializationFor], its mapping only lists the
        // extension classes. The target's own attributes must still be loaded and merged,
        // so make sure the class is always part of its own source classes.
        if (!\in_array($className, $sourceClasses, true)) {
            array_unshift($sourceClasses, $className);
        }

        $success = false;
        foreach ($sourceClasses as $sourceClass) {
            $reflectionClass = $className === $sourceClass ? $classMetadata->getReflectionClass() : new \ReflectionClass($sourceClass);
            $success = $this->doLoadClassMetadata($reflectionClass, $classMetadata) || $success;
        }

        foreach ($discriminatorMapTypes as $type => $mappedClass) {
            $this->addDiscriminatorMapType($type, $mappedClass, $classMetadata);
        }

        if (!$this->deferDiscriminatorMapValidation) {
            $this->validateDiscriminatorMap($classMetadata);
        }

        return $success;
    }

    private function validateDiscriminatorMap(ClassMetadataInterface $classMetadata): void
    {
        if (null !== $mapping = $classMetadata->getClassDiscriminatorMapping()) {
            if (!$mapping->getTypesMapping()) {
                throw new MappingException(\sprintf('Discriminator map for "%s" cannot be empty.', $classMetadata->getName()));
            }

            if (null !== $mapping->getDefaultType() && null === $mapping->getClassForType($mapping->getDefaultType())) {
                throw new MappingException(\sprintf('Default type "%s" for discriminator map of "%s" must be present in mapping types.', $mapping->getDefaultType(), $classMetadata->getName()));
            }
        }
    }

    private function doLoadClassMetadata(\ReflectionClass $reflectionClass, ClassMetadataInterface $classMetadata): bool
    {
        $className = $reflectionClass->name;
        $loaded = false;
        $classGroups = [];
        $classContextAttribute = null;

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        foreach ($this->loadAttributes($reflectionClass) as $attribute) {
            $loaded = true;
            match (true) {
                $attribute instanceof DiscriminatorMap => $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping($attribute->typeProperty, $attribute->mapping, $attribute->defaultType)),
                $attribute instanceof DiscriminatorMapType => $this->checkDiscriminatorMapTypeIsRegistered($attribute, $className),
                $attribute instanceof Groups => $classGroups = $attribute->groups,
                $attribute instanceof Context => $classContextAttribute = $attribute,
                default => null,
            };
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            $attributeMetadata = $attributesMetadata[$property->name];
            if ($property->class === $className) {
                if ($classContextAttribute) {
                    $this->setAttributeContextsForGroups($classContextAttribute, $attributeMetadata);
                }

                foreach ($classGroups as $group) {
                    $attributeMetadata->addGroup($group);
                }

                foreach ($this->loadAttributes($property) as $attribute) {
                    $loaded = true;

                    if ($attribute instanceof Groups) {
                        foreach ($attribute->groups as $group) {
                            $attributeMetadata->addGroup($group);
                        }

                        continue;
                    }

                    match (true) {
                        $attribute instanceof MaxDepth => $attributeMetadata->setMaxDepth($attribute->maxDepth),
                        $attribute instanceof SerializedName => $attributeMetadata->setSerializedName($attribute->serializedName, $attribute->groups),
                        $attribute instanceof SerializedPath => $attributeMetadata->setSerializedPath($attribute->serializedPath, $attribute->groups),
                        $attribute instanceof Ignore => $attributeMetadata->setIgnore(true),
                        $attribute instanceof Context => $this->setAttributeContextsForGroups($attribute, $attributeMetadata),
                        default => null,
                    };
                }
            }
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->class !== $className) {
                continue;
            }
            $name = $method->name;

            if (0 === stripos($name, 'get') && $method->getNumberOfRequiredParameters()) {
                continue; /*  matches the BC behavior in `Symfony\Component\Serializer\Normalizer\ObjectNormalizer::extractAttributes` */
            }

            $attributeName = $this->getAttributeNameFromAccessor($reflectionClass, $method, true);
            $accessorOrMutator = null !== $attributeName;
            $hasProperty = $this->hasPropertyForAccessor($method->getDeclaringClass(), $name);
            $attributeMetadata = null;

            if ($hasProperty || $accessorOrMutator) {
                if (null === $attributeName || 's' !== $name[0] && $hasProperty && $this->hasAttributeNameCollision($reflectionClass, $attributeName, $name)) {
                    $attributeName = $name;
                }

                if (isset($attributesMetadata[$attributeName])) {
                    $attributeMetadata = $attributesMetadata[$attributeName];
                } else {
                    $attributesMetadata[$attributeName] = $attributeMetadata = new AttributeMetadata($attributeName);
                    $classMetadata->addAttributeMetadata($attributeMetadata);
                }
            }

            foreach ($this->loadAttributes($method) as $attribute) {
                if ($attribute instanceof Groups) {
                    if (!$attributeMetadata) {
                        throw new MappingException(\sprintf('Groups on "%s::%s()" cannot be added. Groups can only be added on methods beginning with "get", "is", "has", "can" or "set".', $className, $method->name));
                    }

                    foreach ($attribute->groups as $group) {
                        $attributeMetadata->addGroup($group);
                    }
                } elseif ($attribute instanceof MaxDepth) {
                    if (!$attributeMetadata) {
                        throw new MappingException(\sprintf('MaxDepth on "%s::%s()" cannot be added. MaxDepth can only be added on methods beginning with "get", "is", "has", "can" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setMaxDepth($attribute->maxDepth);
                } elseif ($attribute instanceof SerializedName) {
                    if (!$attributeMetadata) {
                        throw new MappingException(\sprintf('SerializedName on "%s::%s()" cannot be added. SerializedName can only be added on methods beginning with "get", "is", "has", "can" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedName($attribute->serializedName, $attribute->groups);
                } elseif ($attribute instanceof SerializedPath) {
                    if (!$attributeMetadata) {
                        throw new MappingException(\sprintf('SerializedPath on "%s::%s()" cannot be added. SerializedPath can only be added on methods beginning with "get", "is", "has", "can" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedPath($attribute->serializedPath, $attribute->groups);
                } elseif ($attribute instanceof Ignore) {
                    if ($attributeMetadata && !$this->hasPublicPropertyForAccessor($reflectionClass, $attributeName)) {
                        $attributeMetadata->setIgnore(true);
                    }
                } elseif ($attribute instanceof Context) {
                    if (!$attributeMetadata) {
                        throw new MappingException(\sprintf('Context on "%s::%s()" cannot be added. Context can only be added on methods beginning with "get", "is", "has", "can" or "set".', $className, $method->name));
                    }

                    $this->setAttributeContextsForGroups($attribute, $attributeMetadata);
                }

                $loaded = true;
            }
        }

        return $loaded;
    }

    /**
     * @param class-string $mappedClass
     */
    private function addDiscriminatorMapType(string $type, string $mappedClass, ClassMetadataInterface $classMetadata): void
    {
        if (!is_a($mappedClass, $classMetadata->getName(), true)) {
            throw new MappingException(\sprintf('Class "%s" cannot add discriminator map type "%s" for "%s" because it is not a subtype of it.', $mappedClass, $type, $classMetadata->getName()));
        }

        if ($this->deferDiscriminatorMapValidation) {
            // wait for the whole loader chain to run, so that types are added to the
            // final discriminator map, no matter which loader declares it
            $this->deferredDiscriminatorMapTypes[$classMetadata->getName()][] = [$type, $mappedClass];

            return;
        }

        if (null === $mapping = $classMetadata->getClassDiscriminatorMapping()) {
            throw new MappingException(\sprintf('Class "%s" cannot add discriminator map type "%s" for "%s" because the target does not declare a discriminator map.', $mappedClass, $type, $classMetadata->getName()));
        }

        $typesMapping = $mapping->getTypesMapping();
        if (isset($typesMapping[$type]) && $typesMapping[$type] !== $mappedClass) {
            throw new MappingException(\sprintf('Discriminator map type "%s" for "%s" is already mapped to "%s".', $type, $classMetadata->getName(), $typesMapping[$type]));
        }

        $typesMapping[$type] = $mappedClass;
        $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping($mapping->getTypeProperty(), $typesMapping, $mapping->getDefaultType()));
    }

    private function checkDiscriminatorMapTypeIsRegistered(DiscriminatorMapType $attribute, string $className): void
    {
        if (null !== $this->discriminatorMapTypes && $className !== ($this->discriminatorMapTypes[$attribute->class][$attribute->type] ?? null)) {
            throw new MappingException(\sprintf('Class "%s" cannot add discriminator map type "%s" for "%s" because it is not registered with the loader. Make sure the class is in a path scanned for services and not in an excluded one, or register it with the "discriminatorMapTypes" argument of the loader.', $className, $attribute->type, $attribute->class));
        }
    }

    private function loadAttributes(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $reflector): iterable
    {
        foreach ($reflector->getAttributes() as $attribute) {
            if ($this->isKnownAttribute($attribute->getName())) {
                try {
                    yield $attribute->newInstance();
                } catch (\Error $e) {
                    if (\Error::class !== $e::class) {
                        throw $e;
                    }
                    $on = match (true) {
                        $reflector instanceof \ReflectionClass => ' on class '.$reflector->name,
                        $reflector instanceof \ReflectionMethod => \sprintf(' on "%s::%s()"', $reflector->class, $reflector->name),
                        $reflector instanceof \ReflectionProperty => \sprintf(' on "%s::$%s"', $reflector->class, $reflector->name),
                        default => '',
                    };

                    throw new MappingException(\sprintf('Could not instantiate attribute "%s"%s.', $attribute->getName(), $on), 0, $e);
                }
            }
        }
    }

    private function setAttributeContextsForGroups(Context $attribute, AttributeMetadataInterface $attributeMetadata): void
    {
        $context = $attribute->context;
        $groups = $attribute->groups;
        $normalizationContext = $attribute->normalizationContext;
        $denormalizationContext = $attribute->denormalizationContext;

        if ($normalizationContext || $context) {
            $attributeMetadata->setNormalizationContextForGroups($normalizationContext ?: $context, $groups);
        }

        if ($denormalizationContext || $context) {
            $attributeMetadata->setDenormalizationContextForGroups($denormalizationContext ?: $context, $groups);
        }
    }

    private function isKnownAttribute(string $attributeName): bool
    {
        foreach (self::KNOWN_ATTRIBUTES as $knownAttribute) {
            if (is_a($attributeName, $knownAttribute, true)) {
                return true;
            }
        }

        return false;
    }
}
