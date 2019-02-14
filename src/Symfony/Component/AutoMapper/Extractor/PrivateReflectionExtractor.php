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

use Symfony\Component\Inflector\Inflector;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;

/**
 * Extracts all (including private) data using the reflection API.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class PrivateReflectionExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface
{
    /**
     * @internal
     */
    public static $defaultMutatorPrefixes = ['add', 'remove', 'set'];

    /**
     * @internal
     */
    public static $defaultAccessorPrefixes = ['is', 'can', 'get'];

    /**
     * @internal
     */
    public static $defaultArrayMutatorPrefixes = ['add', 'remove'];

    private $mutatorPrefixes;
    private $accessorPrefixes;
    private $arrayMutatorPrefixes;
    private $reflectionExtractor;

    public function __construct(array $mutatorPrefixes = null, array $accessorPrefixes = null, array $arrayMutatorPrefixes = null, bool $enableConstructorExtraction = true)
    {
        $this->mutatorPrefixes = $mutatorPrefixes ?? self::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? self::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? self::$defaultArrayMutatorPrefixes;
        $this->reflectionExtractor = new ReflectionExtractor($mutatorPrefixes, $accessorPrefixes, $arrayMutatorPrefixes, $enableConstructorExtraction);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = [])
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return;
        }

        $propertyFlag = \ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED;
        $methodFlag = \ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_PROTECTED;

        $reflectionProperties = $reflectionClass->getProperties($propertyFlag);
        $properties = $this->reflectionExtractor->getProperties($class, $context);

        if (null === $properties) {
            $properties = [];
        }

        foreach ($reflectionProperties as $reflectionProperty) {
            $properties[$reflectionProperty->name] = $reflectionProperty->name;
        }

        foreach ($reflectionClass->getMethods($methodFlag) as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }

            $propertyName = $this->getPropertyName($reflectionMethod->name, $reflectionProperties);

            if (!$propertyName || isset($properties[$propertyName])) {
                continue;
            }

            if (!$reflectionClass->hasProperty($propertyName) && !preg_match('/^[A-Z]{2,}/', $propertyName)) {
                $propertyName = lcfirst($propertyName);
            }

            $properties[$propertyName] = $propertyName;
        }

        return $properties ? array_values($properties) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = [])
    {
        return $this->reflectionExtractor->getTypes($class, $property, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = [])
    {
        $refClass = new \ReflectionClass($class);

        return $refClass->hasProperty($property);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = [])
    {
        $refClass = new \ReflectionClass($class);

        return $refClass->hasProperty($property);
    }

    private function getPropertyName(string $methodName, array $reflectionProperties): ?string
    {
        $pattern = implode('|', array_merge($this->accessorPrefixes, $this->mutatorPrefixes));

        if ('' !== $pattern && preg_match('/^('.$pattern.')(.+)$/i', $methodName, $matches)) {
            if (!\in_array($matches[1], $this->arrayMutatorPrefixes)) {
                return $matches[2];
            }

            foreach ($reflectionProperties as $reflectionProperty) {
                foreach ((array) Inflector::singularize($reflectionProperty->name) as $name) {
                    if (strtolower($name) === strtolower($matches[2])) {
                        return $reflectionProperty->name;
                    }
                }
            }

            return $matches[2];
        }

        return null;
    }
}
