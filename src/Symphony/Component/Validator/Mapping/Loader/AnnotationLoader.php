<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\Constraints\Callback;
use Symphony\Component\Validator\Constraints\GroupSequence;
use Symphony\Component\Validator\Constraints\GroupSequenceProvider;
use Symphony\Component\Validator\Exception\MappingException;
use Symphony\Component\Validator\Mapping\ClassMetadata;

/**
 * Loads validation metadata using a Doctrine annotation {@link Reader}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AnnotationLoader implements LoaderInterface
{
    protected $reader;

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
        $success = false;

        foreach ($this->reader->getClassAnnotations($reflClass) as $constraint) {
            if ($constraint instanceof GroupSequence) {
                $metadata->setGroupSequence($constraint->groups);
            } elseif ($constraint instanceof GroupSequenceProvider) {
                $metadata->setGroupSequenceProvider(true);
            } elseif ($constraint instanceof Constraint) {
                $metadata->addConstraint($constraint);
            }

            $success = true;
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $constraint) {
                    if ($constraint instanceof Constraint) {
                        $metadata->addPropertyConstraint($property->name, $constraint);
                    }

                    $success = true;
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name === $className) {
                foreach ($this->reader->getMethodAnnotations($method) as $constraint) {
                    if ($constraint instanceof Callback) {
                        $constraint->callback = $method->getName();

                        $metadata->addConstraint($constraint);
                    } elseif ($constraint instanceof Constraint) {
                        if (preg_match('/^(get|is|has)(.+)$/i', $method->name, $matches)) {
                            $metadata->addGetterMethodConstraint(lcfirst($matches[2]), $matches[0], $constraint);
                        } else {
                            throw new MappingException(sprintf('The constraint on "%s::%s" cannot be added. Constraints can only be added on methods beginning with "get", "is" or "has".', $className, $method->name));
                        }
                    }

                    $success = true;
                }
            }
        }

        return $success;
    }
}
