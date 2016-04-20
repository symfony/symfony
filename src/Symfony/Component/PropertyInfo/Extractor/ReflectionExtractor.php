<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Extracts PHP informations using the reflection API.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface
{
    /**
     * @internal
     *
     * @var string[]
     */
    public static $mutatorPrefixes = array('add', 'remove', 'set');

    /**
     * @internal
     *
     * @var string[]
     */
    public static $accessorPrefixes = array('is', 'can', 'get');

    /**
     * @internal
     *
     * @var array[]
     */
    public static $arrayMutatorPrefixes = array('add', 'remove');

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $reflectionException) {
            return;
        }

        $properties = array();
        foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $properties[$reflectionProperty->name] = true;
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $propertyName = $this->getPropertyName($reflectionMethod->name);
            if (!$propertyName || isset($properties[$propertyName])) {
                continue;
            }
            if (!preg_match('/^[A-Z]{2,}/', $propertyName)) {
                $propertyName = lcfirst($propertyName);
            }
            $properties[$propertyName] = true;
        }

        return array_keys($properties);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        if ($fromMutator = $this->extractFromMutator($class, $property)) {
            return $fromMutator;
        }

        if ($fromAccessor = $this->extractFromAccessor($class, $property)) {
            return $fromAccessor;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        if ($this->isPublicProperty($class, $property)) {
            return true;
        }

        list($reflectionMethod) = $this->getAccessorMethod($class, $property);

        return null !== $reflectionMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        if ($this->isPublicProperty($class, $property)) {
            return true;
        }

        list($reflectionMethod) = $this->getMutatorMethod($class, $property);

        return null !== $reflectionMethod;
    }

    /**
     * Tries to extract type information from mutators.
     *
     * @param string $class
     * @param string $property
     *
     * @return Type[]|null
     */
    private function extractFromMutator($class, $property)
    {
        list($reflectionMethod, $prefix) = $this->getMutatorMethod($class, $property);
        if (null === $reflectionMethod) {
            return;
        }

        $reflectionParameters = $reflectionMethod->getParameters();
        $reflectionParameter = $reflectionParameters[0];

        $arrayMutator = in_array($prefix, self::$arrayMutatorPrefixes);

        if (method_exists($reflectionParameter, 'getType') && $reflectionType = $reflectionParameter->getType()) {
            $fromReflectionType = $this->extractFromReflectionType($reflectionType);

            if (!$arrayMutator) {
                return array($fromReflectionType);
            }

            $phpType = Type::BUILTIN_TYPE_ARRAY;
            $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
            $collectionValueType = $fromReflectionType;
        }

        if ($reflectionParameter->isArray()) {
            $phpType = Type::BUILTIN_TYPE_ARRAY;
            $collection = true;
        }

        if ($arrayMutator) {
            $collection = true;
            $nullable = false;
            $collectionNullable = $reflectionParameter->allowsNull();
        } else {
            $nullable = $reflectionParameter->allowsNull();
            $collectionNullable = false;
        }

        if (!isset($collection)) {
            $collection = false;
        }

        if (method_exists($reflectionParameter, 'isCallable') && $reflectionParameter->isCallable()) {
            $phpType = Type::BUILTIN_TYPE_CALLABLE;
        }

        if ($typeHint = $reflectionParameter->getClass()) {
            if ($collection) {
                $phpType = Type::BUILTIN_TYPE_ARRAY;
                $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                $collectionValueType = new Type(Type::BUILTIN_TYPE_OBJECT, $collectionNullable, $typeHint->name);
            } else {
                $phpType = Type::BUILTIN_TYPE_OBJECT;
                $typeClass = $typeHint->name;
            }
        }

        // Nothing useful extracted
        if (!isset($phpType)) {
            return;
        }

        return array(
            new Type(
                $phpType,
                $nullable,
                isset($typeClass) ? $typeClass : null,
                $collection,
                isset($collectionKeyType) ? $collectionKeyType : null,
                isset($collectionValueType) ? $collectionValueType : null
            ),
        );
    }

    /**
     * Tries to extract type information from accessors.
     *
     * @param string $class
     * @param string $property
     *
     * @return Type[]|null
     */
    private function extractFromAccessor($class, $property)
    {
        list($reflectionMethod, $prefix) = $this->getAccessorMethod($class, $property);
        if (null === $reflectionMethod) {
            return;
        }

        if (method_exists($reflectionMethod, 'getReturnType') && $reflectionType = $reflectionMethod->getReturnType()) {
            return array($this->extractFromReflectionType($reflectionType));
        }

        if (in_array($prefix, array('is', 'can'))) {
            return array(new Type(Type::BUILTIN_TYPE_BOOL));
        }
    }

    /**
     * Extracts data from the PHP 7 reflection type.
     *
     * @param \ReflectionType $reflectionType
     *
     * @return Type
     */
    private function extractFromReflectionType(\ReflectionType $reflectionType)
    {
        $phpTypeOrClass = (string) $reflectionType;
        $nullable = $reflectionType->allowsNull();

        if ($reflectionType->isBuiltin()) {
            if (Type::BUILTIN_TYPE_ARRAY === $phpTypeOrClass) {
                $type = new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true);
            } else {
                $type = new Type($phpTypeOrClass, $nullable);
            }
        } else {
            $type = new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $phpTypeOrClass);
        }

        return $type;
    }

    /**
     * Does the class have the given public property?
     *
     * @param string $class
     * @param string $property
     *
     * @return bool
     */
    private function isPublicProperty($class, $property)
    {
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);

            return $reflectionProperty->isPublic();
        } catch (\ReflectionException $reflectionExcetion) {
            // Return false if the property doesn't exist
        }

        return false;
    }

    /**
     * Gets the accessor method.
     *
     * Returns an array with a the instance of \ReflectionMethod as first key
     * and the prefix of the method as second or null if not found.
     *
     * @param string $class
     * @param string $property
     *
     * @return array|null
     */
    private function getAccessorMethod($class, $property)
    {
        $ucProperty = ucfirst($property);

        foreach (self::$accessorPrefixes as $prefix) {
            try {
                $reflectionMethod = new \ReflectionMethod($class, $prefix.$ucProperty);

                if (0 === $reflectionMethod->getNumberOfRequiredParameters()) {
                    return array($reflectionMethod, $prefix);
                }
            } catch (\ReflectionException $reflectionException) {
                // Return null if the property doesn't exist
            }
        }
    }

    /**
     * Gets the mutator method.
     *
     * Returns an array with a the instance of \ReflectionMethod as first key
     * and the prefix of the method as second or null if not found.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    private function getMutatorMethod($class, $property)
    {
        $ucProperty = ucfirst($property);

        foreach (self::$mutatorPrefixes as $prefix) {
            try {
                $reflectionMethod = new \ReflectionMethod($class, $prefix.$ucProperty);

                // Parameter can be optional to allow things like: method(array $foo = null)
                if ($reflectionMethod->getNumberOfParameters() >= 1) {
                    return array($reflectionMethod, $prefix);
                }
            } catch (\ReflectionException $reflectionException) {
                // Try the next prefix if the method doesn't exist
            }
        }
    }

    /**
     * Extracts a property name from a method name.
     *
     * @param string $methodName
     *
     * @return string
     */
    private function getPropertyName($methodName)
    {
        $pattern = implode('|', array_merge(self::$accessorPrefixes, self::$mutatorPrefixes));

        if (preg_match('/^('.$pattern.')(.+)$/i', $methodName, $matches)) {
            return $matches[2];
        }
    }
}
