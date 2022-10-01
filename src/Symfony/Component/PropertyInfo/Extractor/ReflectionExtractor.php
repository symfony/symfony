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

use Symfony\Component\Inflector\Inflector;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Extracts data using the reflection API.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class ReflectionExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface, PropertyInitializableExtractorInterface
{
    /**
     * @internal
     */
    public static $defaultMutatorPrefixes = ['add', 'remove', 'set'];

    /**
     * @internal
     */
    public static $defaultAccessorPrefixes = ['is', 'can', 'get', 'has'];

    /**
     * @internal
     */
    public static $defaultArrayMutatorPrefixes = ['add', 'remove'];

    public const ALLOW_PRIVATE = 1;
    public const ALLOW_PROTECTED = 2;
    public const ALLOW_PUBLIC = 4;

    private const MAP_TYPES = [
        'integer' => Type::BUILTIN_TYPE_INT,
        'boolean' => Type::BUILTIN_TYPE_BOOL,
        'double' => Type::BUILTIN_TYPE_FLOAT,
    ];

    private $mutatorPrefixes;
    private $accessorPrefixes;
    private $arrayMutatorPrefixes;
    private $enableConstructorExtraction;
    private $accessFlags;

    private $arrayMutatorPrefixesFirst;
    private $arrayMutatorPrefixesLast;

    /**
     * @param string[]|null $mutatorPrefixes
     * @param string[]|null $accessorPrefixes
     * @param string[]|null $arrayMutatorPrefixes
     */
    public function __construct(array $mutatorPrefixes = null, array $accessorPrefixes = null, array $arrayMutatorPrefixes = null, bool $enableConstructorExtraction = true, int $accessFlags = self::ALLOW_PUBLIC)
    {
        $this->mutatorPrefixes = $mutatorPrefixes ?? self::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? self::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? self::$defaultArrayMutatorPrefixes;
        $this->enableConstructorExtraction = $enableConstructorExtraction;
        $this->accessFlags = $accessFlags;

        $this->arrayMutatorPrefixesFirst = array_merge($this->arrayMutatorPrefixes, array_diff($this->mutatorPrefixes, $this->arrayMutatorPrefixes));
        $this->arrayMutatorPrefixesLast = array_reverse($this->arrayMutatorPrefixesFirst);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = []): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return null;
        }

        $propertyFlags = 0;
        $methodFlags = 0;

        if ($this->accessFlags & self::ALLOW_PUBLIC) {
            $propertyFlags = $propertyFlags | \ReflectionProperty::IS_PUBLIC;
            $methodFlags = $methodFlags | \ReflectionMethod::IS_PUBLIC;
        }

        if ($this->accessFlags & self::ALLOW_PRIVATE) {
            $propertyFlags = $propertyFlags | \ReflectionProperty::IS_PRIVATE;
            $methodFlags = $methodFlags | \ReflectionMethod::IS_PRIVATE;
        }

        if ($this->accessFlags & self::ALLOW_PROTECTED) {
            $propertyFlags = $propertyFlags | \ReflectionProperty::IS_PROTECTED;
            $methodFlags = $methodFlags | \ReflectionMethod::IS_PROTECTED;
        }

        $reflectionProperties = $reflectionClass->getProperties();

        $properties = [];
        foreach ($reflectionProperties as $reflectionProperty) {
            if ($reflectionProperty->getModifiers() & $propertyFlags) {
                $properties[$reflectionProperty->name] = $reflectionProperty->name;
            }
        }

        foreach ($reflectionClass->getMethods($methodFlags) as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }

            $propertyName = $this->getPropertyName($reflectionMethod->name, $reflectionProperties);
            if (!$propertyName || isset($properties[$propertyName])) {
                continue;
            }
            if ($reflectionClass->hasProperty($lowerCasedPropertyName = lcfirst($propertyName)) || (!$reflectionClass->hasProperty($propertyName) && !preg_match('/^[A-Z]{2,}/', $propertyName))) {
                $propertyName = $lowerCasedPropertyName;
            }
            $properties[$propertyName] = $propertyName;
        }

        return $properties ? array_values($properties) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = []): ?array
    {
        if ($fromMutator = $this->extractFromMutator($class, $property)) {
            return $fromMutator;
        }

        if ($fromAccessor = $this->extractFromAccessor($class, $property)) {
            return $fromAccessor;
        }

        if (
            ($context['enable_constructor_extraction'] ?? $this->enableConstructorExtraction) &&
            $fromConstructor = $this->extractFromConstructor($class, $property)
        ) {
            return $fromConstructor;
        }

        if ($fromPropertyDeclaration = $this->extractFromPropertyDeclaration($class, $property)) {
            return $fromPropertyDeclaration;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = []): ?bool
    {
        if ($this->isAllowedProperty($class, $property)) {
            return true;
        }

        [$reflectionMethod] = $this->getAccessorMethod($class, $property);

        return null !== $reflectionMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = []): ?bool
    {
        if ($this->isAllowedProperty($class, $property, true)) {
            return true;
        }

        [$reflectionMethod] = $this->getMutatorMethod($class, $property);

        return null !== $reflectionMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return null;
        }

        if (!$reflectionClass->isInstantiable()) {
            return false;
        }

        if ($constructor = $reflectionClass->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($property === $parameter->name) {
                    return true;
                }
            }
        } elseif ($parentClass = $reflectionClass->getParentClass()) {
            return $this->isInitializable($parentClass->getName(), $property);
        }

        return false;
    }

    /**
     * @return Type[]|null
     */
    private function extractFromMutator(string $class, string $property): ?array
    {
        [$reflectionMethod, $prefix] = $this->getMutatorMethod($class, $property);
        if (null === $reflectionMethod) {
            return null;
        }

        $reflectionParameters = $reflectionMethod->getParameters();
        $reflectionParameter = $reflectionParameters[0];

        if (!$reflectionType = $reflectionParameter->getType()) {
            return null;
        }
        $type = $this->extractFromReflectionType($reflectionType, $reflectionMethod->getDeclaringClass());

        if (1 === \count($type) && \in_array($prefix, $this->arrayMutatorPrefixes)) {
            $type = [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), $type[0])];
        }

        return $type;
    }

    /**
     * Tries to extract type information from accessors.
     *
     * @return Type[]|null
     */
    private function extractFromAccessor(string $class, string $property): ?array
    {
        [$reflectionMethod, $prefix] = $this->getAccessorMethod($class, $property);
        if (null === $reflectionMethod) {
            return null;
        }

        if ($reflectionType = $reflectionMethod->getReturnType()) {
            return $this->extractFromReflectionType($reflectionType, $reflectionMethod->getDeclaringClass());
        }

        if (\in_array($prefix, ['is', 'can', 'has'])) {
            return [new Type(Type::BUILTIN_TYPE_BOOL)];
        }

        return null;
    }

    /**
     * Tries to extract type information from constructor.
     *
     * @return Type[]|null
     */
    private function extractFromConstructor(string $class, string $property): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return null;
        }

        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($property !== $parameter->name) {
                continue;
            }
            $reflectionType = $parameter->getType();

            return $reflectionType ? $this->extractFromReflectionType($reflectionType, $constructor->getDeclaringClass()) : null;
        }

        if ($parentClass = $reflectionClass->getParentClass()) {
            return $this->extractFromConstructor($parentClass->getName(), $property);
        }

        return null;
    }

    private function extractFromPropertyDeclaration(string $class, string $property): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);

            if (\PHP_VERSION_ID >= 70400) {
                $reflectionProperty = $reflectionClass->getProperty($property);
                $reflectionPropertyType = $reflectionProperty->getType();

                if (null !== $reflectionPropertyType && $types = $this->extractFromReflectionType($reflectionPropertyType, $reflectionProperty->getDeclaringClass())) {
                    return $types;
                }
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        $defaultValue = $reflectionClass->getDefaultProperties()[$property] ?? null;

        if (null === $defaultValue) {
            return null;
        }

        $type = \gettype($defaultValue);

        return [new Type(static::MAP_TYPES[$type] ?? $type, $this->isNullableProperty($class, $property))];
    }

    private function extractFromReflectionType(\ReflectionType $reflectionType, \ReflectionClass $declaringClass): array
    {
        $types = [];
        $nullable = $reflectionType->allowsNull();

        foreach (($reflectionType instanceof \ReflectionUnionType || $reflectionType instanceof \ReflectionIntersectionType) ? $reflectionType->getTypes() : [$reflectionType] as $type) {
            if (!$type instanceof \ReflectionNamedType) {
                // Nested composite types are not supported yet.
                return [];
            }

            $phpTypeOrClass = $type->getName();
            if ('null' === $phpTypeOrClass || 'mixed' === $phpTypeOrClass || 'never' === $phpTypeOrClass) {
                continue;
            }

            if (Type::BUILTIN_TYPE_ARRAY === $phpTypeOrClass) {
                $types[] = new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true);
            } elseif ('void' === $phpTypeOrClass) {
                $types[] = new Type(Type::BUILTIN_TYPE_NULL, $nullable);
            } elseif ($type->isBuiltin()) {
                $types[] = new Type($phpTypeOrClass, $nullable);
            } else {
                $types[] = new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $this->resolveTypeName($phpTypeOrClass, $declaringClass));
            }
        }

        return $types;
    }

    private function resolveTypeName(string $name, \ReflectionClass $declaringClass): string
    {
        if ('self' === $lcName = strtolower($name)) {
            return $declaringClass->name;
        }
        if ('parent' === $lcName && $parent = $declaringClass->getParentClass()) {
            return $parent->name;
        }

        return $name;
    }

    private function isNullableProperty(string $class, string $property): bool
    {
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);

            if (\PHP_VERSION_ID >= 70400) {
                $reflectionPropertyType = $reflectionProperty->getType();

                return null !== $reflectionPropertyType && $reflectionPropertyType->allowsNull();
            }

            return false;
        } catch (\ReflectionException $e) {
            // Return false if the property doesn't exist
        }

        return false;
    }

    private function isAllowedProperty(string $class, string $property, bool $writeAccessRequired = false): bool
    {
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);

            if (\PHP_VERSION_ID >= 80100 && $writeAccessRequired && $reflectionProperty->isReadOnly()) {
                return false;
            }

            if ($this->accessFlags & self::ALLOW_PUBLIC && $reflectionProperty->isPublic()) {
                return true;
            }

            if ($this->accessFlags & self::ALLOW_PROTECTED && $reflectionProperty->isProtected()) {
                return true;
            }

            if ($this->accessFlags & self::ALLOW_PRIVATE && $reflectionProperty->isPrivate()) {
                return true;
            }

            return false;
        } catch (\ReflectionException $e) {
            // Return false if the property doesn't exist
        }

        return false;
    }

    /**
     * Gets the accessor method.
     *
     * Returns an array with a the instance of \ReflectionMethod as first key
     * and the prefix of the method as second or null if not found.
     */
    private function getAccessorMethod(string $class, string $property): ?array
    {
        $ucProperty = ucfirst($property);

        foreach ($this->accessorPrefixes as $prefix) {
            try {
                $reflectionMethod = new \ReflectionMethod($class, $prefix.$ucProperty);
                if ($reflectionMethod->isStatic()) {
                    continue;
                }

                if (0 === $reflectionMethod->getNumberOfRequiredParameters()) {
                    return [$reflectionMethod, $prefix];
                }
            } catch (\ReflectionException $e) {
                // Return null if the property doesn't exist
            }
        }

        return null;
    }

    /**
     * Returns an array with a the instance of \ReflectionMethod as first key
     * and the prefix of the method as second or null if not found.
     */
    private function getMutatorMethod(string $class, string $property): ?array
    {
        $ucProperty = ucfirst($property);
        $ucSingulars = (array) Inflector::singularize($ucProperty);

        $mutatorPrefixes = \in_array($ucProperty, $ucSingulars, true) ? $this->arrayMutatorPrefixesLast : $this->arrayMutatorPrefixesFirst;

        foreach ($mutatorPrefixes as $prefix) {
            $names = [$ucProperty];
            if (\in_array($prefix, $this->arrayMutatorPrefixes)) {
                $names = array_merge($names, $ucSingulars);
            }

            foreach ($names as $name) {
                try {
                    $reflectionMethod = new \ReflectionMethod($class, $prefix.$name);
                    if ($reflectionMethod->isStatic()) {
                        continue;
                    }

                    // Parameter can be optional to allow things like: method(array $foo = null)
                    if ($reflectionMethod->getNumberOfParameters() >= 1) {
                        return [$reflectionMethod, $prefix];
                    }
                } catch (\ReflectionException $e) {
                    // Try the next prefix if the method doesn't exist
                }
            }
        }

        return null;
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
