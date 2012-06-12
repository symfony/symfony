<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\GroupSequenceProvider;
use Symfony\Component\Validator\Constraint;

class AnnotationLoader implements LoaderInterface
{
    protected $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();
        $className = $reflClass->name;
        $loaded = false;

        foreach ($this->reader->getClassAnnotations($reflClass) as $constraint) {
            if ($constraint instanceof GroupSequence) {
                $metadata->setGroupSequence($constraint->groups);
            } elseif ($constraint instanceof GroupSequenceProvider) {
                $metadata->setGroupSequenceProvider(true);
            } elseif ($constraint instanceof Constraint) {
                $metadata->addConstraint($constraint);
            }

            $loaded = true;
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->name == $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $constraint) {
                    if ($constraint instanceof Constraint) {
                        $metadata->addPropertyConstraint($property->name, $constraint);
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name ==  $className) {
                foreach ($this->reader->getMethodAnnotations($method) as $constraint) {
                    if ($constraint instanceof Constraint) {
                        if (preg_match('/^(get|is)(.+)$/i', $method->name, $matches)) {
                            $metadata->addGetterConstraint(lcfirst($matches[2]), $constraint);
                        } else {
                            throw new MappingException(sprintf('The constraint on "%s::%s" cannot be added. Constraints can only be added on methods beginning with "get" or "is".', $className, $method->name));
                        }
                    }

                    $loaded = true;
                }
            }
        }

        return $loaded;
    }
}
