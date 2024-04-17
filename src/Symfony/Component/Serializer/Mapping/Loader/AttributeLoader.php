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

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
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
class AttributeLoader implements LoaderInterface
{
    private const KNOWN_ATTRIBUTES = [
        DiscriminatorMap::class,
        Groups::class,
        Ignore::class,
        MaxDepth::class,
        SerializedName::class,
        SerializedPath::class,
        Context::class,
    ];

    public function __construct(
        private readonly ?Reader $reader = null,
    ) {
        if ($reader) {
            trigger_deprecation('symfony/serializer', '6.4', 'Passing a "%s" instance as argument 1 to "%s()" is deprecated, pass null or omit the parameter instead.', get_debug_type($reader), __METHOD__);
        }
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;
        $loaded = false;
        $classGroups = [];
        $classContextAnnotation = null;

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        foreach ($this->loadAttributes($reflectionClass) as $annotation) {
            if ($annotation instanceof DiscriminatorMap) {
                $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping(
                    $annotation->getTypeProperty(),
                    $annotation->getMapping()
                ));
                continue;
            }

            if ($annotation instanceof Groups) {
                $classGroups = $annotation->getGroups();

                continue;
            }

            if ($annotation instanceof Context) {
                $classContextAnnotation = $annotation;
            }
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name === $className) {
                if ($classContextAnnotation) {
                    $this->setAttributeContextsForGroups($classContextAnnotation, $attributesMetadata[$property->name]);
                }

                foreach ($classGroups as $group) {
                    $attributesMetadata[$property->name]->addGroup($group);
                }

                foreach ($this->loadAttributes($property) as $annotation) {
                    if ($annotation instanceof Groups) {
                        foreach ($annotation->getGroups() as $group) {
                            $attributesMetadata[$property->name]->addGroup($group);
                        }
                    } elseif ($annotation instanceof MaxDepth) {
                        $attributesMetadata[$property->name]->setMaxDepth($annotation->getMaxDepth());
                    } elseif ($annotation instanceof SerializedName) {
                        $attributesMetadata[$property->name]->setSerializedName($annotation->getSerializedName());
                    } elseif ($annotation instanceof SerializedPath) {
                        $attributesMetadata[$property->name]->setSerializedPath($annotation->getSerializedPath());
                    } elseif ($annotation instanceof Ignore) {
                        $attributesMetadata[$property->name]->setIgnore(true);
                    } elseif ($annotation instanceof Context) {
                        $this->setAttributeContextsForGroups($annotation, $attributesMetadata[$property->name]);
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name !== $className) {
                continue;
            }

            if (0 === stripos($method->name, 'get') && $method->getNumberOfRequiredParameters()) {
                continue; /*  matches the BC behavior in `Symfony\Component\Serializer\Normalizer\ObjectNormalizer::extractAttributes` */
            }

            $accessorOrMutator = preg_match('/^(get|is|has|set)(.+)$/i', $method->name, $matches);
            if ($accessorOrMutator) {
                $attributeName = lcfirst($matches[2]);

                if (isset($attributesMetadata[$attributeName])) {
                    $attributeMetadata = $attributesMetadata[$attributeName];
                } else {
                    $attributesMetadata[$attributeName] = $attributeMetadata = new AttributeMetadata($attributeName);
                    $classMetadata->addAttributeMetadata($attributeMetadata);
                }
            }

            foreach ($this->loadAttributes($method) as $annotation) {
                if ($annotation instanceof Groups) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(sprintf('Groups on "%s::%s()" cannot be added. Groups can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    foreach ($annotation->getGroups() as $group) {
                        $attributeMetadata->addGroup($group);
                    }
                } elseif ($annotation instanceof MaxDepth) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(sprintf('MaxDepth on "%s::%s()" cannot be added. MaxDepth can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setMaxDepth($annotation->getMaxDepth());
                } elseif ($annotation instanceof SerializedName) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(sprintf('SerializedName on "%s::%s()" cannot be added. SerializedName can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedName($annotation->getSerializedName());
                } elseif ($annotation instanceof SerializedPath) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(sprintf('SerializedPath on "%s::%s()" cannot be added. SerializedPath can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedPath($annotation->getSerializedPath());
                } elseif ($annotation instanceof Ignore) {
                    if ($accessorOrMutator) {
                        $attributeMetadata->setIgnore(true);
                    }
                } elseif ($annotation instanceof Context) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(sprintf('Context on "%s::%s()" cannot be added. Context can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $this->setAttributeContextsForGroups($annotation, $attributeMetadata);
                }

                $loaded = true;
            }
        }

        return $loaded;
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
                        $reflector instanceof \ReflectionMethod => sprintf(' on "%s::%s()"', $reflector->getDeclaringClass()->name, $reflector->name),
                        $reflector instanceof \ReflectionProperty => sprintf(' on "%s::$%s"', $reflector->getDeclaringClass()->name, $reflector->name),
                        default => '',
                    };

                    throw new MappingException(sprintf('Could not instantiate attribute "%s"%s.', $attribute->getName(), $on), 0, $e);
                }
            }
        }

        if (null === $this->reader) {
            return;
        }

        if ($reflector instanceof \ReflectionClass) {
            yield from $this->getClassAnnotations($reflector);
        }
        if ($reflector instanceof \ReflectionMethod) {
            yield from $this->getMethodAnnotations($reflector);
        }
        if ($reflector instanceof \ReflectionProperty) {
            yield from $this->getPropertyAnnotations($reflector);
        }
    }

    /**
     * @deprecated since Symfony 6.4 without replacement
     */
    public function loadAnnotations(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $reflector): iterable
    {
        trigger_deprecation('symfony/serializer', '6.4', 'Method "%s()" is deprecated without replacement.', __METHOD__);

        return $this->loadAttributes($reflector);
    }

    private function setAttributeContextsForGroups(Context $annotation, AttributeMetadataInterface $attributeMetadata): void
    {
        if ($annotation->getContext()) {
            $attributeMetadata->setNormalizationContextForGroups($annotation->getContext(), $annotation->getGroups());
            $attributeMetadata->setDenormalizationContextForGroups($annotation->getContext(), $annotation->getGroups());
        }

        if ($annotation->getNormalizationContext()) {
            $attributeMetadata->setNormalizationContextForGroups($annotation->getNormalizationContext(), $annotation->getGroups());
        }

        if ($annotation->getDenormalizationContext()) {
            $attributeMetadata->setDenormalizationContextForGroups($annotation->getDenormalizationContext(), $annotation->getGroups());
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

    /**
     * @return object[]
     */
    private function getClassAnnotations(\ReflectionClass $reflector): array
    {
        if ($annotations = array_filter(
            $this->reader->getClassAnnotations($reflector),
            fn (object $annotation): bool => $this->isKnownAttribute($annotation::class),
        )) {
            trigger_deprecation('symfony/serializer', '6.4', 'Class "%s" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.', $reflector->getName());
        }

        return $annotations;
    }

    /**
     * @return object[]
     */
    private function getMethodAnnotations(\ReflectionMethod $reflector): array
    {
        if ($annotations = array_filter(
            $this->reader->getMethodAnnotations($reflector),
            fn (object $annotation): bool => $this->isKnownAttribute($annotation::class),
        )) {
            trigger_deprecation('symfony/serializer', '6.4', 'Method "%s::%s()" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.', $reflector->getDeclaringClass()->getName(), $reflector->getName());
        }

        return $annotations;
    }

    /**
     * @return object[]
     */
    private function getPropertyAnnotations(\ReflectionProperty $reflector): array
    {
        if ($annotations = array_filter(
            $this->reader->getPropertyAnnotations($reflector),
            fn (object $annotation): bool => $this->isKnownAttribute($annotation::class),
        )) {
            trigger_deprecation('symfony/serializer', '6.4', 'Property "%s::$%s" uses Doctrine Annotations to configure serialization, which is deprecated. Use PHP attributes instead.', $reflector->getDeclaringClass()->getName(), $reflector->getName());
        }

        return $annotations;
    }
}

if (!class_exists(AnnotationLoader::class, false)) {
    class_alias(AttributeLoader::class, AnnotationLoader::class);
}
