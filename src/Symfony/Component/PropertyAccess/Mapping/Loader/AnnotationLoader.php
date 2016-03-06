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
use Symfony\Component\PropertyAccess\Annotation\PropertyAccessor;
use Symfony\Component\PropertyAccess\Mapping\AttributeMetadata;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadataInterface;

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
                    if ($annotation instanceof PropertyAccessor) {
                        $attributesMetadata[$property->name]->setGetter($annotation->getGetter());
                        $attributesMetadata[$property->name]->setSetter($annotation->getSetter());
                        $attributesMetadata[$property->name]->setAdder($annotation->getAdder());
                        $attributesMetadata[$property->name]->setRemover($annotation->getRemover());
                    }

                    $loaded = true;
                }
            }
        }

        return $loaded;
    }
}
