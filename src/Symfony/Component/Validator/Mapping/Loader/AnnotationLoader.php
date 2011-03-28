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

use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Validator\Constraints\Set;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraint;

class AnnotationLoader implements LoaderInterface
{
    protected $reader;

    public function __construct(array $paths = null)
    {
        if (null === $paths) {
            $paths = array('assert' => 'Symfony\\Component\\Validator\\Constraints\\');
        }

        $this->reader = new AnnotationReader();
        $this->reader->setAutoloadAnnotations(true);

        foreach ($paths as $prefix => $path) {
            $this->reader->setAnnotationNamespaceAlias($path, $prefix);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $reflClass = $metadata->getReflectionClass();
        $className = $reflClass->getName();
        $loaded = false;

        foreach ($this->reader->getClassAnnotations($reflClass) as $constraint) {
            if ($constraint instanceof Set) {
                foreach ($constraint->constraints as $constraint) {
                    $metadata->addConstraint($constraint);
                }
            } elseif ($constraint instanceof GroupSequence) {
                $metadata->setGroupSequence($constraint->groups);
            } elseif ($constraint instanceof Constraint) {
                $metadata->addConstraint($constraint);
            }

            $loaded = true;
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() == $className) {
                foreach ($this->reader->getPropertyAnnotations($property) as $constraint) {
                    if ($constraint instanceof Set) {
                        foreach ($constraint->constraints as $constraint) {
                            $metadata->addPropertyConstraint($property->getName(), $constraint);
                        }
                    } elseif ($constraint instanceof Constraint) {
                        $metadata->addPropertyConstraint($property->getName(), $constraint);
                    }

                    $loaded = true;
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() ==  $className) {
                foreach ($this->reader->getMethodAnnotations($method) as $constraint) {
                    // TODO: clean this up
                    $name = lcfirst(substr($method->getName(), 0, 3)=='get' ? substr($method->getName(), 3) : substr($method->getName(), 2));

                    if ($constraint instanceof Set) {
                        foreach ($constraint->constraints as $constraint) {
                            $metadata->addGetterConstraint($name, $constraint);
                        }
                    } elseif ($constraint instanceof Constraint) {
                        $metadata->addGetterConstraint($name, $constraint);
                    }

                    $loaded = true;
                }
            }
        }

        return $loaded;
    }
}
