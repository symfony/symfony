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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\GroupSequenceProvider;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Loads validation metadata using a Doctrine annotation {@link Reader} or using PHP 8 attributes.
 *
 * @deprecated since Symfony 6.4, use {@see AttributeLoader} instead
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
class AnnotationLoader implements LoaderInterface
{
    /**
     * @deprecated since Symfony 6.4, this property will be removed in 7.0
     *
     * @var Reader|null
     */
    protected $reader;

    public function __construct(?Reader $reader = null)
    {
        $this->reader = $reader;
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $reflClass = $metadata->getReflectionClass();
        $className = $reflClass->name;
        $success = false;

        foreach ($this->getAnnotations($reflClass) as $constraint) {
            if ($constraint instanceof GroupSequence) {
                $metadata->setGroupSequence($constraint->groups);
            } elseif ($constraint instanceof GroupSequenceProvider) {
                $metadata->setGroupProvider($constraint->provider);
                $metadata->setGroupSequenceProvider(true);
            } elseif ($constraint instanceof Constraint) {
                $metadata->addConstraint($constraint);
            }

            $success = true;
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->name === $className) {
                foreach ($this->getAnnotations($property) as $constraint) {
                    if ($constraint instanceof Constraint) {
                        $metadata->addPropertyConstraint($property->name, $constraint);
                    }

                    $success = true;
                }
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($method->getDeclaringClass()->name === $className) {
                foreach ($this->getAnnotations($method) as $constraint) {
                    if ($constraint instanceof Callback) {
                        $constraint->callback = $method->getName();

                        $metadata->addConstraint($constraint);
                    } elseif ($constraint instanceof Constraint) {
                        if (preg_match('/^(get|is|has)(.+)$/i', $method->name, $matches)) {
                            $metadata->addGetterMethodConstraint(lcfirst($matches[2]), $matches[0], $constraint);
                        } else {
                            throw new MappingException(sprintf('The constraint on "%s::%s()" cannot be added. Constraints can only be added on methods beginning with "get", "is" or "has".', $className, $method->name));
                        }
                    }

                    $success = true;
                }
            }
        }

        return $success;
    }

    private function getAnnotations(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $reflection): iterable
    {
        $dedup = [];

        foreach ($reflection->getAttributes(GroupSequence::class) as $attribute) {
            $dedup[] = $attribute->newInstance();
            yield $attribute->newInstance();
        }
        foreach ($reflection->getAttributes(GroupSequenceProvider::class) as $attribute) {
            $dedup[] = $attribute->newInstance();
            yield $attribute->newInstance();
        }
        foreach ($reflection->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $dedup[] = $attribute->newInstance();
            yield $attribute->newInstance();
        }
        if (!$this->reader) {
            return;
        }

        $annotations = [];

        if ($reflection instanceof \ReflectionClass && $annotations = $this->reader->getClassAnnotations($reflection)) {
            $this->triggerDeprecationIfAnnotationIsUsed($annotations, sprintf('Class "%s"', $reflection->getName()));
        }
        if ($reflection instanceof \ReflectionMethod && $annotations = $this->reader->getMethodAnnotations($reflection)) {
            $this->triggerDeprecationIfAnnotationIsUsed($annotations, sprintf('Method "%s::%s()"', $reflection->getDeclaringClass()->getName(), $reflection->getName()));
        }
        if ($reflection instanceof \ReflectionProperty && $annotations = $this->reader->getPropertyAnnotations($reflection)) {
            $this->triggerDeprecationIfAnnotationIsUsed($annotations, sprintf('Property "%s::$%s"', $reflection->getDeclaringClass()->getName(), $reflection->getName()));
        }

        foreach ($dedup as $annotation) {
            if ($annotation instanceof Constraint) {
                $annotation->groups; // trigger initialization of the "groups" property
            }
        }

        foreach ($annotations as $annotation) {
            if ($annotation instanceof Constraint) {
                $annotation->groups; // trigger initialization of the "groups" property
            }
            if (!\in_array($annotation, $dedup, false)) {
                yield $annotation;
            }
        }
    }

    private function triggerDeprecationIfAnnotationIsUsed(array $annotations, string $messagePrefix): void
    {
        foreach ($annotations as $annotation) {
            if (
                $annotation instanceof Constraint
                || $annotation instanceof GroupSequence
                || $annotation instanceof GroupSequenceProvider
            ) {
                trigger_deprecation('symfony/validator', '6.4', sprintf('%s uses Doctrine Annotations to configure validation constraints, which is deprecated. Use PHP attributes instead.', $messagePrefix));
                break;
            }
        }
    }
}
