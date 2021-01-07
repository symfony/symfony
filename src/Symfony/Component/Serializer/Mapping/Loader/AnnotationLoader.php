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
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Loader for Doctrine annotations and PHP 8 attributes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
class AnnotationLoader implements LoaderInterface
{
    private const KNOWN_ANNOTATIONS = [
        DiscriminatorMap::class => true,
        Groups::class => true,
        Ignore::class => true,
        MaxDepth::class => true,
        SerializedName::class => true,
    ];

    private $reader;

    public function __construct(Reader $reader = null)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;
        $loaded = false;

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        foreach ($this->loadAnnotations($reflectionClass) as $annotation) {
            if ($annotation instanceof DiscriminatorMap) {
                $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping(
                    $annotation->getTypeProperty(),
                    $annotation->getMapping()
                ));
            }
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->loadAnnotations($property) as $annotation) {
                    if ($annotation instanceof Groups) {
                        foreach ($annotation->getGroups() as $group) {
                            $attributesMetadata[$property->name]->addGroup($group);
                        }
                    } elseif ($annotation instanceof MaxDepth) {
                        $attributesMetadata[$property->name]->setMaxDepth($annotation->getMaxDepth());
                    } elseif ($annotation instanceof SerializedName) {
                        $attributesMetadata[$property->name]->setSerializedName($annotation->getSerializedName());
                    } elseif ($annotation instanceof Ignore) {
                        $attributesMetadata[$property->name]->setIgnore(true);
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name !== $className) {
                continue;
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

            foreach ($this->loadAnnotations($method) as $annotation) {
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
                } elseif ($annotation instanceof Ignore) {
                    $attributeMetadata->setIgnore(true);
                }

                $loaded = true;
            }
        }

        return $loaded;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector
     */
    public function loadAnnotations(object $reflector): iterable
    {
        if (\PHP_VERSION_ID >= 80000) {
            foreach ($reflector->getAttributes() as $attribute) {
                if (self::KNOWN_ANNOTATIONS[$attribute->getName()] ?? false) {
                    yield $attribute->newInstance();
                }
            }
        }

        if (null === $this->reader) {
            return;
        }

        if ($reflector instanceof \ReflectionClass) {
            yield from $this->reader->getClassAnnotations($reflector);
        }
        if ($reflector instanceof \ReflectionMethod) {
            yield from $this->reader->getMethodAnnotations($reflector);
        }
        if ($reflector instanceof \ReflectionProperty) {
            yield from $this->reader->getPropertyAnnotations($reflector);
        }
    }
}
