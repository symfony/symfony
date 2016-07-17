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
use Symfony\Component\Serializer\Annotation;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Annotation loader.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class BetterAnnotationLoader implements LoaderInterface
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
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

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $annotation) {
                    if ($annotation instanceof Annotation\Groups) {
                        foreach ($annotation->getGroups() as $group) {
                            $attributesMetadata[$property->name]->addGroup($group);
                        }
                    } elseif ($annotation instanceof Annotation\Accessor) {
                        $attributesMetadata[$property->name]->setAccessorGetter($annotation->getGetter());
                        $attributesMetadata[$property->name]->setAccessorSetter($annotation->getSetter());
                    } elseif ($annotation instanceof Annotation\Exclude) {
                        $attributesMetadata[$property->name]->setExclude($annotation->getValue());
                    } elseif ($annotation instanceof Annotation\Expose) {
                        $attributesMetadata[$property->name]->setExpose($annotation->getValue());
                    } elseif ($annotation instanceof Annotation\MaxDepth) {
                        $attributesMetadata[$property->name]->setMaxDepth($annotation->getMaxDepth());
                    } elseif ($annotation instanceof Annotation\ReadOnly) {
                        $attributesMetadata[$property->name]->setReadOnly($annotation->getReadOnly());
                    } elseif ($annotation instanceof Annotation\SerializedName) {
                        $attributesMetadata[$property->name]->setSerializedName($annotation->getName());
                    } elseif ($annotation instanceof Annotation\Type) {
                        $attributesMetadata[$property->name]->setType($annotation->getType());
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name !== $className) {
                continue;
            }

            // Verify if method is an accessor or mutator for a property
            $attributeName = $methodName = $method->name;

            if (isset($attributesMetadata[$attributeName])) {
                // If we have a method with same name as a property, we ignore the method
                continue;
            }

            $attributesMetadata[$attributeName] = $attributeMetadata = new AttributeMetadata($attributeName);
            $classMetadata->addAttributeMetadata($attributeMetadata);

            // Add default values for methods
            $attributeMetadata->setAccessorGetter($methodName);
            $attributeMetadata->setExclude(true);
            $attributeMetadata->setReadOnly(true);

            foreach ($this->reader->getMethodAnnotations($method) as $annotation) {
                if ($annotation instanceof Annotation\Groups) {
                    foreach ($annotation->getGroups() as $group) {
                        $attributeMetadata->addGroup($group);
                    }
                } elseif ($annotation instanceof Annotation\Expose) {
                    $attributeMetadata->setExpose($annotation->getValue());
                    $attributeMetadata->setExclude(!$annotation->getValue());
                } elseif ($annotation instanceof Annotation\MaxDepth) {
                    $attributeMetadata->setMaxDepth($annotation->getMaxDepth());
                } elseif ($annotation instanceof Annotation\SerializedName) {
                    $attributeMetadata->setSerializedName($annotation->getName());
                }
            }

            $loaded = true;
        }

        foreach ($this->reader->getClassAnnotations($reflectionClass) as $annotation) {
            if ($annotation instanceof Annotation\ExclusionPolicy) {
                $classMetadata->setExclusionPolicy($annotation->getPolicy());
            } elseif ($annotation instanceof Annotation\ReadOnly) {
                $classMetadata->setReadOnly($annotation->getReadOnly());
            }

            $loaded = true;
        }

        return $loaded;
    }
}
