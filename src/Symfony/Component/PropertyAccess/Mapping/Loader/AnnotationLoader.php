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
use Symfony\Component\PropertyAccess\Annotation\Property;
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

        $propertiesMetadata = $classMetadata->getPropertiesMetadata();

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

        return $loaded;
    }
}
