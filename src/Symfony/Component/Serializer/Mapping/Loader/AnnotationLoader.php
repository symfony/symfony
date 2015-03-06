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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\ClassMetadata;

/**
 * Annotation loader.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
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
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();
        $className = $reflClass->name;
        $loaded = false;

        foreach ($reflClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $groups) {
                    if ($groups instanceof Groups) {
                        foreach ($groups->getGroups() as $group) {
                            $metadata->addAttributeGroup($property->name, $group);
                        }
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getMethodAnnotations($method) as $groups) {
                    if ($groups instanceof Groups) {
                        if (preg_match('/^(get|is)(.+)$/i', $method->name, $matches)) {
                            foreach ($groups->getGroups() as $group) {
                                $metadata->addAttributeGroup(lcfirst($matches[2]), $group);
                            }
                        } else {
                            throw new MappingException(sprintf('Groups on "%s::%s" cannot be added. Groups can only be added on methods beginning with "get" or "is".', $className, $method->name));
                        }
                    }

                    $loaded = true;
                }
            }
        }

        return $loaded;
    }
}
