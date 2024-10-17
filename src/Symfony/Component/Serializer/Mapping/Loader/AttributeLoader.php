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

    public function __construct()
    {
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;
        $loaded = false;
        $classGroups = [];
        $classContextAttribute = null;

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        foreach ($this->loadAttributes($reflectionClass) as $attribute) {
            if ($attribute instanceof DiscriminatorMap) {
                $classMetadata->setClassDiscriminatorMapping(new ClassDiscriminatorMapping(
                    $attribute->getTypeProperty(),
                    $attribute->getMapping()
                ));
                continue;
            }

            if ($attribute instanceof Groups) {
                $classGroups = $attribute->getGroups();

                continue;
            }

            if ($attribute instanceof Context) {
                $classContextAttribute = $attribute;
            }
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name === $className) {
                if ($classContextAttribute) {
                    $this->setAttributeContextsForGroups($classContextAttribute, $attributesMetadata[$property->name]);
                }

                foreach ($classGroups as $group) {
                    $attributesMetadata[$property->name]->addGroup($group);
                }

                foreach ($this->loadAttributes($property) as $attribute) {
                    if ($attribute instanceof Groups) {
                        foreach ($attribute->getGroups() as $group) {
                            $attributesMetadata[$property->name]->addGroup($group);
                        }
                    } elseif ($attribute instanceof MaxDepth) {
                        $attributesMetadata[$property->name]->setMaxDepth($attribute->getMaxDepth());
                    } elseif ($attribute instanceof SerializedName) {
                        $attributesMetadata[$property->name]->setSerializedName($attribute->getSerializedName());
                    } elseif ($attribute instanceof SerializedPath) {
                        $attributesMetadata[$property->name]->setSerializedPath($attribute->getSerializedPath());
                    } elseif ($attribute instanceof Ignore) {
                        $attributesMetadata[$property->name]->setIgnore(true);
                    } elseif ($attribute instanceof Context) {
                        $this->setAttributeContextsForGroups($attribute, $attributesMetadata[$property->name]);
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

            foreach ($this->loadAttributes($method) as $attribute) {
                if ($attribute instanceof Groups) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(\sprintf('Groups on "%s::%s()" cannot be added. Groups can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    foreach ($attribute->getGroups() as $group) {
                        $attributeMetadata->addGroup($group);
                    }
                } elseif ($attribute instanceof MaxDepth) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(\sprintf('MaxDepth on "%s::%s()" cannot be added. MaxDepth can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setMaxDepth($attribute->getMaxDepth());
                } elseif ($attribute instanceof SerializedName) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(\sprintf('SerializedName on "%s::%s()" cannot be added. SerializedName can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedName($attribute->getSerializedName());
                } elseif ($attribute instanceof SerializedPath) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(\sprintf('SerializedPath on "%s::%s()" cannot be added. SerializedPath can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $attributeMetadata->setSerializedPath($attribute->getSerializedPath());
                } elseif ($attribute instanceof Ignore) {
                    if ($accessorOrMutator) {
                        $attributeMetadata->setIgnore(true);
                    }
                } elseif ($attribute instanceof Context) {
                    if (!$accessorOrMutator) {
                        throw new MappingException(\sprintf('Context on "%s::%s()" cannot be added. Context can only be added on methods beginning with "get", "is", "has" or "set".', $className, $method->name));
                    }

                    $this->setAttributeContextsForGroups($attribute, $attributeMetadata);
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
                        $reflector instanceof \ReflectionMethod => \sprintf(' on "%s::%s()"', $reflector->getDeclaringClass()->name, $reflector->name),
                        $reflector instanceof \ReflectionProperty => \sprintf(' on "%s::$%s"', $reflector->getDeclaringClass()->name, $reflector->name),
                        default => '',
                    };

                    throw new MappingException(\sprintf('Could not instantiate attribute "%s"%s.', $attribute->getName(), $on), 0, $e);
                }
            }
        }
    }

    private function setAttributeContextsForGroups(Context $attribute, AttributeMetadataInterface $attributeMetadata): void
    {
        if ($attribute->getContext()) {
            $attributeMetadata->setNormalizationContextForGroups($attribute->getContext(), $attribute->getGroups());
            $attributeMetadata->setDenormalizationContextForGroups($attribute->getContext(), $attribute->getGroups());
        }

        if ($attribute->getNormalizationContext()) {
            $attributeMetadata->setNormalizationContextForGroups($attribute->getNormalizationContext(), $attribute->getGroups());
        }

        if ($attribute->getDenormalizationContext()) {
            $attributeMetadata->setDenormalizationContextForGroups($attribute->getDenormalizationContext(), $attribute->getGroups());
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
