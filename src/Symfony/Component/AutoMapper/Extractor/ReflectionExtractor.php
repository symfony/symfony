<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Extractor;

/**
 * Extracts accessor and mutator from reflection.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ReflectionExtractor implements AccessorExtractorInterface
{
    private $allowPrivate;

    public function __construct($allowPrivate = false)
    {
        $this->allowPrivate = $allowPrivate;
    }

    public function getReadAccessor(string $class, string $property): ?ReadAccessor
    {
        $reflClass = new \ReflectionClass($class);
        $hasProperty = $reflClass->hasProperty($property);
        $camelProp = $this->camelize($property);
        $getter = 'get'.$camelProp;
        $getsetter = lcfirst($camelProp); // jQuery style, e.g. read: last(), write: last($item)
        $isser = 'is'.$camelProp;
        $hasser = 'has'.$camelProp;
        $accessPrivate = false;

        if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            $accessType = ReadAccessor::TYPE_METHOD;
            $accessName = $getter;
        } elseif ($reflClass->hasMethod($getsetter) && $reflClass->getMethod($getsetter)->isPublic()) {
            $accessType = ReadAccessor::TYPE_METHOD;
            $accessName = $getsetter;
        } elseif ($reflClass->hasMethod($isser) && $reflClass->getMethod($isser)->isPublic()) {
            $accessType = ReadAccessor::TYPE_METHOD;
            $accessName = $isser;
        } elseif ($reflClass->hasMethod($hasser) && $reflClass->getMethod($hasser)->isPublic()) {
            $accessType = ReadAccessor::TYPE_METHOD;
            $accessName = $hasser;
        } elseif ($reflClass->hasMethod('__get') && $reflClass->getMethod('__get')->isPublic()) {
            $accessType = ReadAccessor::TYPE_PROPERTY;
            $accessName = $property;
        } elseif ($hasProperty && $reflClass->getProperty($property)->isPublic()) {
            $accessType = ReadAccessor::TYPE_PROPERTY;
            $accessName = $property;
        } elseif ($hasProperty && $this->allowPrivate && $reflClass->getProperty($property)) {
            $accessType = ReadAccessor::TYPE_PROPERTY;
            $accessName = $property;
            $accessPrivate = true;
        } else {
            return null;
        }

        return new ReadAccessor($accessType, $accessName, $accessPrivate);
    }

    public function getWriteMutator(string $class, string $property, bool $allowConstruct = true): ?WriteMutator
    {
        $reflClass = new \ReflectionClass($class);
        $hasProperty = $reflClass->hasProperty($property);
        $camelized = $this->camelize($property);
        $accessParameter = null;
        $accessName = null;
        $accessType = null;
        $accessPrivate = false;
        $constructor = $reflClass->getConstructor();

        if (null !== $constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getName() === $property) {
                    $accessParameter = $parameter;
                }
            }
        }

        if (null === $accessType) {
            $setter = 'set'.$camelized;
            $getsetter = lcfirst($camelized); // jQuery style, e.g. read: last(), write: last($item)

            if (null !== $accessParameter && $allowConstruct) {
                $accessType = WriteMutator::TYPE_CONSTRUCTOR;
                $accessName = $property;
            } elseif ($this->isMethodAccessible($reflClass, $setter, 1)) {
                $accessType = WriteMutator::TYPE_METHOD;
                $accessName = $setter;
            } elseif ($this->isMethodAccessible($reflClass, $getsetter, 1)) {
                $accessType = WriteMutator::TYPE_METHOD;
                $accessName = $getsetter;
            } elseif ($this->isMethodAccessible($reflClass, '__set', 2)) {
                $accessType = WriteMutator::TYPE_PROPERTY;
                $accessName = $property;
            } elseif ($hasProperty && $reflClass->getProperty($property)->isPublic()) {
                $accessType = WriteMutator::TYPE_PROPERTY;
                $accessName = $property;
            } elseif ($hasProperty && $this->allowPrivate && $reflClass->getProperty($property)) {
                $accessType = WriteMutator::TYPE_PROPERTY;
                $accessName = $property;
                $accessPrivate = true;
            } else {
                return null;
            }
        }

        return new WriteMutator($accessType, $accessName, $accessPrivate, $accessParameter);
    }

    /**
     * Returns whether a method is public and has the number of required parameters.
     */
    private function isMethodAccessible(\ReflectionClass $class, string $methodName, int $parameters): bool
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic()
                && $method->getNumberOfRequiredParameters() <= $parameters
                && $method->getNumberOfParameters() >= $parameters) {
                return true;
            }
        }

        return false;
    }

    /**
     * Camelizes a given string.
     */
    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
