<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\PropertyAccess\Annotation\PropertyAdder;
use Symfony\Component\PropertyAccess\Annotation\PropertyGetter;
use Symfony\Component\PropertyAccess\Annotation\Property;
use Symfony\Component\PropertyAccess\Annotation\PropertyRemover;
use Symfony\Component\PropertyAccess\Annotation\PropertySetter;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

/**
 * Annotation loader.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class AnnotationLoader implements LoaderInterface
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
    public function loadClassMetadata(ClassMetadata $classMetadata)
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;
        $loaded = false;

        $propertiesMetadata = $classMetadata->getPropertyMetadataCollection();

        foreach ($reflectionClass->getProperties() as $property) {
            if (!isset($propertiesMetadata[$property->name])) {
                $propertiesMetadata[$property->name] = new PropertyMetadata($property->name);
                $classMetadata->addPropertyMetadata($propertiesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $annotation) {
                    if ($annotation instanceof Property) {
                        $propertiesMetadata[$property->name]->setGetter($annotation->getter);
                        $propertiesMetadata[$property->name]->setSetter($annotation->setter);
                        $propertiesMetadata[$property->name]->setAdder($annotation->adder);
                        $propertiesMetadata[$property->name]->setRemover($annotation->remover);
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name === $className) {

                foreach ($this->reader->getMethodAnnotations($method) as $annotation) {
                    if ($annotation instanceof PropertyGetter) {
                        if (!isset($propertiesMetadata[$annotation->property])) {
                            $propertiesMetadata[$annotation->property] = new PropertyMetadata($annotation->property);
                            $classMetadata->addPropertyMetadata($propertiesMetadata[$annotation->property]);
                        }
                        $propertiesMetadata[$annotation->property]->setGetter($method->getName());
                    }
                    if ($annotation instanceof PropertySetter) {
                        if (!isset($propertiesMetadata[$annotation->property])) {
                            $propertiesMetadata[$annotation->property] = new PropertyMetadata($annotation->property);
                            $classMetadata->addPropertyMetadata($propertiesMetadata[$annotation->property]);
                        }
                        $propertiesMetadata[$annotation->property]->setSetter($method->getName());
                    }
                    if ($annotation instanceof PropertyAdder) {
                        if (!isset($propertiesMetadata[$annotation->property])) {
                            $propertiesMetadata[$annotation->property] = new PropertyMetadata($annotation->property);
                            $classMetadata->addPropertyMetadata($propertiesMetadata[$annotation->property]);
                        }
                        $propertiesMetadata[$annotation->property]->setAdder($method->getName());
                    }
                    if ($annotation instanceof PropertyRemover) {
                        if (!isset($propertiesMetadata[$annotation->property])) {
                            $propertiesMetadata[$annotation->property] = new PropertyMetadata($annotation->property);
                            $classMetadata->addPropertyMetadata($propertiesMetadata[$annotation->property]);
                        }
                        $propertiesMetadata[$annotation->property]->setRemover($method->getName());
                    }

                    $loaded = true;
                }
            }
        }

        return $loaded;
    }
}
