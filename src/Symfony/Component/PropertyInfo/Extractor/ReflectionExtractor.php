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
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Extracts data using the reflection API.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class ReflectionExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface, PropertyInitializableExtractorInterface, PropertyReadInfoExtractorInterface, PropertyWriteInfoExtractorInterface, ConstructorArgumentTypeExtractorInterface
{
    /**
     * @internal
     */
    public static array $defaultMutatorPrefixes = ['add', 'remove', 'set'];

    /**
     * @internal
     */
    public static array $defaultAccessorPrefixes = ['get', 'is', 'has', 'can'];

    /**
     * @internal
     */
    public static array $defaultArrayMutatorPrefixes = ['add', 'remove'];

    public const ALLOW_PRIVATE = 1;
    public const ALLOW_PROTECTED = 2;
    public const ALLOW_PUBLIC = 4;

    /** @var int Allow none of the magic methods */
    public const DISALLOW_MAGIC_METHODS = 0;
    /** @var int Allow magic __get methods */
    public const ALLOW_MAGIC_GET = 1 << 0;
    /** @var int Allow magic __set methods */
    public const ALLOW_MAGIC_SET = 1 << 1;
    /** @var int Allow magic __call methods */
    public const ALLOW_MAGIC_CALL = 1 << 2;

    private const MAP_TYPES = [
        'integer' => TypeIdentifier::INT->value,
        'boolean' => TypeIdentifier::BOOL->value,
        'double' => TypeIdentifier::FLOAT->value,
    ];

    private array $mutatorPrefixes;
    private array $accessorPrefixes;
    private array $arrayMutatorPrefixes;
    private bool $enableConstructorExtraction;
    private int $methodReflectionFlags;
    private int $magicMethodsFlags;
    private int $propertyReflectionFlags;
    private InflectorInterface $inflector;
    private array $arrayMutatorPrefixesFirst;
    private array $arrayMutatorPrefixesLast;
    private TypeResolverInterface $typeResolver;

    /**
     * @param string[]|null $mutatorPrefixes
     * @param string[]|null $accessorPrefixes
     * @param string[]|null $arrayMutatorPrefixes
     */
    public function __construct(?array $mutatorPrefixes = null, ?array $accessorPrefixes = null, ?array $arrayMutatorPrefixes = null, bool $enableConstructorExtraction = true, int $accessFlags = self::ALLOW_PUBLIC, ?InflectorInterface $inflector = null, int $magicMethodsFlags = self::ALLOW_MAGIC_GET | self::ALLOW_MAGIC_SET)
    {
        $this->mutatorPrefixes = $mutatorPrefixes ?? self::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? self::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? self::$defaultArrayMutatorPrefixes;
        $this->enableConstructorExtraction = $enableConstructorExtraction;
        $this->methodReflectionFlags = $this->getMethodsFlags($accessFlags);
        $this->propertyReflectionFlags = $this->getPropertyFlags($accessFlags);
        $this->magicMethodsFlags = $magicMethodsFlags;
        $this->inflector = $inflector ?? new EnglishInflector();
        $this->typeResolver = TypeResolver::create();

        $this->arrayMutatorPrefixesFirst = array_merge($this->arrayMutatorPrefixes, array_diff($this->mutatorPrefixes, $this->arrayMutatorPrefixes));
        $this->arrayMutatorPrefixesLast = array_reverse($this->arrayMutatorPrefixesFirst);
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        $reflectionProperties = $reflectionClass->getProperties();

        $properties = [];
        foreach ($reflectionProperties as $reflectionProperty) {
            if ($reflectionProperty->getModifiers() & $this->propertyReflectionFlags) {
                $properties[$reflectionProperty->name] = $reflectionProperty->name;
            }
        }

        foreach ($reflectionClass->getMethods($this->methodReflectionFlags) as $reflectionMethod) {
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

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        if ($fromMutator = $this->extractFromMutator($class, $property)) {
            return $fromMutator;
        }

        if ($fromAccessor = $this->extractFromAccessor($class, $property)) {
            return $fromAccessor;
        }

        if (
            ($context['enable_constructor_extraction'] ?? $this->enableConstructorExtraction)
            && $fromConstructor = $this->extractFromConstructor($class, $property)
        ) {
            return $fromConstructor;
        }

        if ($fromPropertyDeclaration = $this->extractFromPropertyDeclaration($class, $property)) {
            return $fromPropertyDeclaration;
        }

        return null;
    }

    /**
     * @return LegacyType[]|null
     */
    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }
        if (!$reflectionConstructor = $reflection->getConstructor()) {
            return null;
        }
        if (!$reflectionParameter = $this->getReflectionParameterFromConstructor($property, $reflectionConstructor)) {
            return null;
        }
        if (!$reflectionType = $reflectionParameter->getType()) {
            return null;
        }
        if (!$types = $this->extractFromReflectionType($reflectionType, $reflectionConstructor->getDeclaringClass())) {
            return null;
        }

        return $types;
    }

    /**
     * @experimental
     */
    public function getType(string $class, string $property, array $context = []): ?Type
    {
        [$mutatorReflection, $prefix] = $this->getMutatorMethod($class, $property);

        if ($mutatorReflection) {
            try {
                $type = $this->typeResolver->resolve($mutatorReflection->getParameters()[0]);

                if (!$type instanceof CollectionType && \in_array($prefix, $this->arrayMutatorPrefixes, true)) {
                    $type = $this->isNullableProperty($class, $property) ? Type::nullable(Type::list($type)) : Type::list($type);
                }

                return $type;
            } catch (UnsupportedException) {
            }
        }

        [$accessorReflection, $prefix] = $this->getAccessorMethod($class, $property);
        if ($accessorReflection) {
            try {
                return $this->typeResolver->resolve($accessorReflection);
            } catch (UnsupportedException) {
            }
        }

        if ($context['enable_constructor_extraction'] ?? $this->enableConstructorExtraction) {
            try {
                $reflectionClass = new \ReflectionClass($class);
                if ($type = $this->extractTypeFromConstructor($reflectionClass, $property)) {
                    return $type;
                }
            } catch (\ReflectionException) {
            }
        }

        try {
            $reflectionClass = new \ReflectionClass($class);
            $reflectionProperty = $reflectionClass->getProperty($property);
        } catch (\ReflectionException) {
            return null;
        }

        try {
            return $this->typeResolver->resolve($reflectionProperty);
        } catch (UnsupportedException) {
        }

        if (null === $defaultValue = ($reflectionClass->getDefaultProperties()[$property] ?? null)) {
            return null;
        }

        $typeIdentifier = TypeIdentifier::from(static::MAP_TYPES[\gettype($defaultValue)] ?? \gettype($defaultValue));
        $type = 'array' === $typeIdentifier->value ? Type::array() : Type::builtin($typeIdentifier);

        if ($this->isNullableProperty($class, $property)) {
            $type = Type::nullable($type);
        }

        return $type;
    }

    /**
     * @experimental
     */
    public function getTypeFromConstructor(string $class, string $property): ?Type
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        if (!$reflectionConstructor = $reflection->getConstructor()) {
            return null;
        }
        if (!$reflectionParameter = $this->getReflectionParameterFromConstructor($property, $reflectionConstructor)) {
            return null;
        }

        try {
            return $this->typeResolver->resolve($reflectionParameter);
        } catch (UnsupportedException) {
            return null;
        }
    }

    private function getReflectionParameterFromConstructor(string $property, \ReflectionMethod $reflectionConstructor): ?\ReflectionParameter
    {
        foreach ($reflectionConstructor->getParameters() as $reflectionParameter) {
            if ($reflectionParameter->getName() === $property) {
                return $reflectionParameter;
            }
        }

        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        if ($this->isAllowedProperty($class, $property)) {
            return true;
        }

        return null !== $this->getReadInfo($class, $property, $context);
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        if ($this->isAllowedProperty($class, $property, true)) {
            return true;
        }

        // First test with the camelized property name
        [$reflectionMethod] = $this->getMutatorMethod($class, $this->camelize($property));
        if (null !== $reflectionMethod) {
            return true;
        }

        // Otherwise check for the old way
        [$reflectionMethod] = $this->getMutatorMethod($class, $property);

        return null !== $reflectionMethod;
    }

    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
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

    public function getReadInfo(string $class, string $property, array $context = []): ?PropertyReadInfo
    {
        try {
            $reflClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        $allowGetterSetter = $context['enable_getter_setter_extraction'] ?? false;
        $magicMethods = $context['enable_magic_methods_extraction'] ?? $this->magicMethodsFlags;
        $allowMagicCall = (bool) ($magicMethods & self::ALLOW_MAGIC_CALL);
        $allowMagicGet = (bool) ($magicMethods & self::ALLOW_MAGIC_GET);
        $hasProperty = $reflClass->hasProperty($property);
        $camelProp = $this->camelize($property);
        $getsetter = lcfirst($camelProp); // jQuery style, e.g. read: last(), write: last($item)

        foreach ($this->accessorPrefixes as $prefix) {
            $methodName = $prefix.$camelProp;

            if ($reflClass->hasMethod($methodName) && $reflClass->getMethod($methodName)->getModifiers() & $this->methodReflectionFlags && !$reflClass->getMethod($methodName)->getNumberOfRequiredParameters()) {
                $method = $reflClass->getMethod($methodName);

                return new PropertyReadInfo(PropertyReadInfo::TYPE_METHOD, $methodName, $this->getReadVisiblityForMethod($method), $method->isStatic(), false);
            }
        }

        if ($allowGetterSetter && $reflClass->hasMethod($getsetter) && ($reflClass->getMethod($getsetter)->getModifiers() & $this->methodReflectionFlags)) {
            $method = $reflClass->getMethod($getsetter);

            return new PropertyReadInfo(PropertyReadInfo::TYPE_METHOD, $getsetter, $this->getReadVisiblityForMethod($method), $method->isStatic(), false);
        }

        if ($allowMagicGet && $reflClass->hasMethod('__get') && (($r = $reflClass->getMethod('__get'))->getModifiers() & $this->methodReflectionFlags)) {
            return new PropertyReadInfo(PropertyReadInfo::TYPE_PROPERTY, $property, PropertyReadInfo::VISIBILITY_PUBLIC, false, $r->returnsReference());
        }

        if ($hasProperty && (($r = $reflClass->getProperty($property))->getModifiers() & $this->propertyReflectionFlags)) {
            return new PropertyReadInfo(PropertyReadInfo::TYPE_PROPERTY, $property, $this->getReadVisiblityForProperty($r), $r->isStatic(), true);
        }

        if ($allowMagicCall && $reflClass->hasMethod('__call') && ($reflClass->getMethod('__call')->getModifiers() & $this->methodReflectionFlags)) {
            return new PropertyReadInfo(PropertyReadInfo::TYPE_METHOD, 'get'.$camelProp, PropertyReadInfo::VISIBILITY_PUBLIC, false, false);
        }

        return null;
    }

    public function getWriteInfo(string $class, string $property, array $context = []): ?PropertyWriteInfo
    {
        try {
            $reflClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        $allowGetterSetter = $context['enable_getter_setter_extraction'] ?? false;
        $magicMethods = $context['enable_magic_methods_extraction'] ?? $this->magicMethodsFlags;
        $allowMagicCall = (bool) ($magicMethods & self::ALLOW_MAGIC_CALL);
        $allowMagicSet = (bool) ($magicMethods & self::ALLOW_MAGIC_SET);
        $allowConstruct = $context['enable_constructor_extraction'] ?? $this->enableConstructorExtraction;
        $allowAdderRemover = $context['enable_adder_remover_extraction'] ?? true;

        $camelized = $this->camelize($property);
        $constructor = $reflClass->getConstructor();
        $singulars = $this->inflector->singularize($camelized);
        $errors = [];

        if (null !== $constructor && $allowConstruct) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getName() === $property) {
                    return new PropertyWriteInfo(PropertyWriteInfo::TYPE_CONSTRUCTOR, $property);
                }
            }
        }

        [$adderAccessName, $removerAccessName, $adderAndRemoverErrors] = $this->findAdderAndRemover($reflClass, $singulars);
        if ($allowAdderRemover && null !== $adderAccessName && null !== $removerAccessName) {
            $adderMethod = $reflClass->getMethod($adderAccessName);
            $removerMethod = $reflClass->getMethod($removerAccessName);

            $mutator = new PropertyWriteInfo(PropertyWriteInfo::TYPE_ADDER_AND_REMOVER);
            $mutator->setAdderInfo(new PropertyWriteInfo(PropertyWriteInfo::TYPE_METHOD, $adderAccessName, $this->getWriteVisiblityForMethod($adderMethod), $adderMethod->isStatic()));
            $mutator->setRemoverInfo(new PropertyWriteInfo(PropertyWriteInfo::TYPE_METHOD, $removerAccessName, $this->getWriteVisiblityForMethod($removerMethod), $removerMethod->isStatic()));

            return $mutator;
        }

        $errors[] = $adderAndRemoverErrors;

        foreach ($this->mutatorPrefixes as $mutatorPrefix) {
            $methodName = $mutatorPrefix.$camelized;

            [$accessible, $methodAccessibleErrors] = $this->isMethodAccessible($reflClass, $methodName, 1);
            if (!$accessible) {
                $errors[] = $methodAccessibleErrors;
                continue;
            }

            $method = $reflClass->getMethod($methodName);

            if (!\in_array($mutatorPrefix, $this->arrayMutatorPrefixes, true)) {
                return new PropertyWriteInfo(PropertyWriteInfo::TYPE_METHOD, $methodName, $this->getWriteVisiblityForMethod($method), $method->isStatic());
            }
        }

        $getsetter = lcfirst($camelized);

        if ($allowGetterSetter) {
            [$accessible, $methodAccessibleErrors] = $this->isMethodAccessible($reflClass, $getsetter, 1);
            if ($accessible) {
                $method = $reflClass->getMethod($getsetter);

                return new PropertyWriteInfo(PropertyWriteInfo::TYPE_METHOD, $getsetter, $this->getWriteVisiblityForMethod($method), $method->isStatic());
            }

            $errors[] = $methodAccessibleErrors;
        }

        if ($reflClass->hasProperty($property) && ($reflClass->getProperty($property)->getModifiers() & $this->propertyReflectionFlags)) {
            $reflProperty = $reflClass->getProperty($property);
            if (!$reflProperty->isReadOnly()) {
                return new PropertyWriteInfo(PropertyWriteInfo::TYPE_PROPERTY, $property, $this->getWriteVisiblityForProperty($reflProperty), $reflProperty->isStatic());
            }

            $errors[] = [sprintf('The property "%s" in class "%s" is a promoted readonly property.', $property, $reflClass->getName())];
            $allowMagicSet = $allowMagicCall = false;
        }

        if ($allowMagicSet) {
            [$accessible, $methodAccessibleErrors] = $this->isMethodAccessible($reflClass, '__set', 2);
            if ($accessible) {
                return new PropertyWriteInfo(PropertyWriteInfo::TYPE_PROPERTY, $property, PropertyWriteInfo::VISIBILITY_PUBLIC, false);
            }

            $errors[] = $methodAccessibleErrors;
        }

        if ($allowMagicCall) {
            [$accessible, $methodAccessibleErrors] = $this->isMethodAccessible($reflClass, '__call', 2);
            if ($accessible) {
                return new PropertyWriteInfo(PropertyWriteInfo::TYPE_METHOD, 'set'.$camelized, PropertyWriteInfo::VISIBILITY_PUBLIC, false);
            }

            $errors[] = $methodAccessibleErrors;
        }

        if (!$allowAdderRemover && null !== $adderAccessName && null !== $removerAccessName) {
            $errors[] = [sprintf(
                'The property "%s" in class "%s" can be defined with the methods "%s()" but '.
                'the new value must be an array or an instance of \Traversable',
                $property,
                $reflClass->getName(),
                implode('()", "', [$adderAccessName, $removerAccessName])
            )];
        }

        $noneProperty = new PropertyWriteInfo();
        $noneProperty->setErrors(array_merge([], ...$errors));

        return $noneProperty;
    }

    /**
     * @return LegacyType[]|null
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

        if (1 === \count($type) && \in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            $type = [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $this->isNullableProperty($class, $property), null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $type[0])];
        }

        return $type;
    }

    /**
     * Tries to extract type information from accessors.
     *
     * @return LegacyType[]|null
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
            return [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)];
        }

        return null;
    }

    /**
     * Tries to extract type information from constructor.
     *
     * @return LegacyType[]|null
     */
    private function extractFromConstructor(string $class, string $property): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
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

            $reflectionProperty = $reflectionClass->getProperty($property);
            $reflectionPropertyType = $reflectionProperty->getType();

            if (null !== $reflectionPropertyType && $types = $this->extractFromReflectionType($reflectionPropertyType, $reflectionProperty->getDeclaringClass())) {
                return $types;
            }
        } catch (\ReflectionException) {
            return null;
        }

        $defaultValue = $reflectionClass->getDefaultProperties()[$property] ?? null;

        if (null === $defaultValue) {
            return null;
        }

        $type = \gettype($defaultValue);
        $type = static::MAP_TYPES[$type] ?? $type;

        return [new LegacyType($type, $this->isNullableProperty($class, $property), null, LegacyType::BUILTIN_TYPE_ARRAY === $type)];
    }

    private function extractTypeFromConstructor(\ReflectionClass $reflectionClass, string $property): ?Type
    {
        if (!$constructor = $reflectionClass->getConstructor()) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($property !== $parameter->name) {
                continue;
            }

            try {
                return $this->typeResolver->resolve($parameter);
            } catch (UnsupportedException) {
            }
        }

        if ($parentClass = $reflectionClass->getParentClass()) {
            return $this->extractTypeFromConstructor($parentClass, $property);
        }

        return null;
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

            if (LegacyType::BUILTIN_TYPE_ARRAY === $phpTypeOrClass) {
                $types[] = new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true);
            } elseif ('void' === $phpTypeOrClass) {
                $types[] = new LegacyType(LegacyType::BUILTIN_TYPE_NULL, $nullable);
            } elseif ($type->isBuiltin()) {
                $types[] = new LegacyType($phpTypeOrClass, $nullable);
            } else {
                $types[] = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, $this->resolveTypeName($phpTypeOrClass, $declaringClass));
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

            $reflectionPropertyType = $reflectionProperty->getType();

            return null !== $reflectionPropertyType && $reflectionPropertyType->allowsNull();
        } catch (\ReflectionException) {
            // Return false if the property doesn't exist
        }

        return false;
    }

    private function isAllowedProperty(string $class, string $property, bool $writeAccessRequired = false): bool
    {
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);

            if ($writeAccessRequired && $reflectionProperty->isReadOnly()) {
                return false;
            }

            return (bool) ($reflectionProperty->getModifiers() & $this->propertyReflectionFlags);
        } catch (\ReflectionException) {
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
            } catch (\ReflectionException) {
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
        $ucSingulars = $this->inflector->singularize($ucProperty);

        $mutatorPrefixes = \in_array($ucProperty, $ucSingulars, true) ? $this->arrayMutatorPrefixesLast : $this->arrayMutatorPrefixesFirst;

        foreach ($mutatorPrefixes as $prefix) {
            $names = [$ucProperty];
            if (\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
                $names = array_merge($names, $ucSingulars);
            }

            foreach ($names as $name) {
                try {
                    $reflectionMethod = new \ReflectionMethod($class, $prefix.$name);
                    if ($reflectionMethod->isStatic()) {
                        continue;
                    }

                    // Parameter can be optional to allow things like: method(?array $foo = null)
                    if ($reflectionMethod->getNumberOfParameters() >= 1) {
                        return [$reflectionMethod, $prefix];
                    }
                } catch (\ReflectionException) {
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
            if (!\in_array($matches[1], $this->arrayMutatorPrefixes, true)) {
                return $matches[2];
            }

            foreach ($reflectionProperties as $reflectionProperty) {
                foreach ($this->inflector->singularize($reflectionProperty->name) as $name) {
                    if (strtolower($name) === strtolower($matches[2])) {
                        return $reflectionProperty->name;
                    }
                }
            }

            return $matches[2];
        }

        return null;
    }

    /**
     * Searches for add and remove methods.
     *
     * @param \ReflectionClass $reflClass The reflection class for the given object
     * @param array            $singulars The singular form of the property name or null
     *
     * @return array An array containing the adder and remover when found and errors
     */
    private function findAdderAndRemover(\ReflectionClass $reflClass, array $singulars): array
    {
        if (2 !== \count($this->arrayMutatorPrefixes)) {
            return [null, null, []];
        }

        [$addPrefix, $removePrefix] = $this->arrayMutatorPrefixes;
        $errors = [];

        foreach ($singulars as $singular) {
            $addMethod = $addPrefix.$singular;
            $removeMethod = $removePrefix.$singular;

            [$addMethodFound, $addMethodAccessibleErrors] = $this->isMethodAccessible($reflClass, $addMethod, 1);
            [$removeMethodFound, $removeMethodAccessibleErrors] = $this->isMethodAccessible($reflClass, $removeMethod, 1);
            $errors[] = $addMethodAccessibleErrors;
            $errors[] = $removeMethodAccessibleErrors;

            if ($addMethodFound && $removeMethodFound) {
                return [$addMethod, $removeMethod, []];
            }

            if ($addMethodFound && !$removeMethodFound) {
                $errors[] = [sprintf('The add method "%s" in class "%s" was found, but the corresponding remove method "%s" was not found', $addMethod, $reflClass->getName(), $removeMethod)];
            } elseif (!$addMethodFound && $removeMethodFound) {
                $errors[] = [sprintf('The remove method "%s" in class "%s" was found, but the corresponding add method "%s" was not found', $removeMethod, $reflClass->getName(), $addMethod)];
            }
        }

        return [null, null, array_merge([], ...$errors)];
    }

    /**
     * Returns whether a method is public and has the number of required parameters and errors.
     */
    private function isMethodAccessible(\ReflectionClass $class, string $methodName, int $parameters): array
    {
        $errors = [];

        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if (\ReflectionMethod::IS_PUBLIC === $this->methodReflectionFlags && !$method->isPublic()) {
                $errors[] = sprintf('The method "%s" in class "%s" was found but does not have public access.', $methodName, $class->getName());
            } elseif ($method->getNumberOfRequiredParameters() > $parameters || $method->getNumberOfParameters() < $parameters) {
                $errors[] = sprintf('The method "%s" in class "%s" requires %d arguments, but should accept only %d.', $methodName, $class->getName(), $method->getNumberOfRequiredParameters(), $parameters);
            } else {
                return [true, $errors];
            }
        }

        return [false, $errors];
    }

    /**
     * Camelizes a given string.
     */
    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * Return allowed reflection method flags.
     */
    private function getMethodsFlags(int $accessFlags): int
    {
        $methodFlags = 0;

        if ($accessFlags & self::ALLOW_PUBLIC) {
            $methodFlags |= \ReflectionMethod::IS_PUBLIC;
        }

        if ($accessFlags & self::ALLOW_PRIVATE) {
            $methodFlags |= \ReflectionMethod::IS_PRIVATE;
        }

        if ($accessFlags & self::ALLOW_PROTECTED) {
            $methodFlags |= \ReflectionMethod::IS_PROTECTED;
        }

        return $methodFlags;
    }

    /**
     * Return allowed reflection property flags.
     */
    private function getPropertyFlags(int $accessFlags): int
    {
        $propertyFlags = 0;

        if ($accessFlags & self::ALLOW_PUBLIC) {
            $propertyFlags |= \ReflectionProperty::IS_PUBLIC;
        }

        if ($accessFlags & self::ALLOW_PRIVATE) {
            $propertyFlags |= \ReflectionProperty::IS_PRIVATE;
        }

        if ($accessFlags & self::ALLOW_PROTECTED) {
            $propertyFlags |= \ReflectionProperty::IS_PROTECTED;
        }

        return $propertyFlags;
    }

    private function getReadVisiblityForProperty(\ReflectionProperty $reflectionProperty): string
    {
        if ($reflectionProperty->isPrivate()) {
            return PropertyReadInfo::VISIBILITY_PRIVATE;
        }

        if ($reflectionProperty->isProtected()) {
            return PropertyReadInfo::VISIBILITY_PROTECTED;
        }

        return PropertyReadInfo::VISIBILITY_PUBLIC;
    }

    private function getReadVisiblityForMethod(\ReflectionMethod $reflectionMethod): string
    {
        if ($reflectionMethod->isPrivate()) {
            return PropertyReadInfo::VISIBILITY_PRIVATE;
        }

        if ($reflectionMethod->isProtected()) {
            return PropertyReadInfo::VISIBILITY_PROTECTED;
        }

        return PropertyReadInfo::VISIBILITY_PUBLIC;
    }

    private function getWriteVisiblityForProperty(\ReflectionProperty $reflectionProperty): string
    {
        if ($reflectionProperty->isPrivate()) {
            return PropertyWriteInfo::VISIBILITY_PRIVATE;
        }

        if ($reflectionProperty->isProtected()) {
            return PropertyWriteInfo::VISIBILITY_PROTECTED;
        }

        return PropertyWriteInfo::VISIBILITY_PUBLIC;
    }

    private function getWriteVisiblityForMethod(\ReflectionMethod $reflectionMethod): string
    {
        if ($reflectionMethod->isPrivate()) {
            return PropertyWriteInfo::VISIBILITY_PRIVATE;
        }

        if ($reflectionMethod->isProtected()) {
            return PropertyWriteInfo::VISIBILITY_PROTECTED;
        }

        return PropertyWriteInfo::VISIBILITY_PUBLIC;
    }
}
