<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

use Symfony\Component\TypeInfo\Type as TypeInfoType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

trigger_deprecation('symfony/property-info', '7.1', 'The "%s" class is deprecated. Use "%s" from the "symfony/type-info" component instead.', Type::class, TypeInfoType::class);

/**
 * Type value object (immutable).
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @deprecated since Symfony 7.1, use "Symfony\Component\TypeInfo\Type" from the "symfony/type-info" component instead
 *
 * @final
 */
class Type
{
    public const BUILTIN_TYPE_INT = 'int';
    public const BUILTIN_TYPE_FLOAT = 'float';
    public const BUILTIN_TYPE_STRING = 'string';
    public const BUILTIN_TYPE_BOOL = 'bool';
    public const BUILTIN_TYPE_RESOURCE = 'resource';
    public const BUILTIN_TYPE_OBJECT = 'object';
    public const BUILTIN_TYPE_ARRAY = 'array';
    public const BUILTIN_TYPE_NULL = 'null';
    public const BUILTIN_TYPE_FALSE = 'false';
    public const BUILTIN_TYPE_TRUE = 'true';
    public const BUILTIN_TYPE_CALLABLE = 'callable';
    public const BUILTIN_TYPE_ITERABLE = 'iterable';

    /**
     * List of PHP builtin types.
     *
     * @var string[]
     */
    public static array $builtinTypes = [
        self::BUILTIN_TYPE_INT,
        self::BUILTIN_TYPE_FLOAT,
        self::BUILTIN_TYPE_STRING,
        self::BUILTIN_TYPE_BOOL,
        self::BUILTIN_TYPE_RESOURCE,
        self::BUILTIN_TYPE_OBJECT,
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_CALLABLE,
        self::BUILTIN_TYPE_FALSE,
        self::BUILTIN_TYPE_TRUE,
        self::BUILTIN_TYPE_NULL,
        self::BUILTIN_TYPE_ITERABLE,
    ];

    /**
     * List of PHP builtin collection types.
     *
     * @var string[]
     */
    public static array $builtinCollectionTypes = [
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_ITERABLE,
    ];

    /**
     * @internal
     */
    public TypeInfoType $internalType;

    private ?bool $isNullable = null;

    private bool $isCollection = false;

    /**
     * @param Type[]|Type|null $collectionKeyType
     * @param Type[]|Type|null $collectionValueType
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $builtinType, bool $nullable = false, ?string $class = null, bool $collection = false, array|self|null $collectionKeyType = null, array|self|null $collectionValueType = null)
    {
        $this->internalType = self::createTypeFromLegacyValues(
            $builtinType,
            $nullable,
            $class,
            $collection,
            $this->validateCollectionArgument($collectionKeyType, 5, '$collectionKeyType') ?? [],
            $this->validateCollectionArgument($collectionValueType, 6, '$collectionValueType') ?? [],
        );

        $this->isNullable = $nullable;
        $this->isCollection = $collection;
    }

    /**
     * Gets built-in type.
     *
     * Can be bool, int, float, string, array, object, resource, null, callback or iterable.
     */
    public function getBuiltinType(): string
    {
        $internalType = self::unwrapNullableType($this->internalType);

        return $internalType->getBaseType()->getTypeIdentifier()->value;
    }

    public function isNullable(): ?bool
    {
        return $this->isNullable;
    }

    /**
     * Gets the class name.
     *
     * Only applicable if the built-in type is object.
     */
    public function getClassName(): ?string
    {
        $internalType = self::unwrapNullableType($this->internalType);
        $internalType = $internalType->getBaseType();

        if (!$internalType instanceof ObjectType) {
            return null;
        }

        return $internalType->getClassName();
    }

    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    /**
     * Gets collection key types.
     *
     * Only applicable for a collection type.
     *
     * @return Type[]
     */
    public function getCollectionKeyTypes(): array
    {
        $internalType = self::unwrapNullableType($this->internalType);

        if ($internalType instanceof CollectionType) {
            return self::convertTypeToLegacyTypes($internalType->getCollectionKeyType()) ?? [];
        }

        if ($internalType instanceof GenericType) {
            return self::convertTypeToLegacyTypes($internalType->getVariableTypes()[0]) ?? [];
        }

        return [];
    }

    /**
     * Gets collection value types.
     *
     * Only applicable for a collection type.
     *
     * @return Type[]
     */
    public function getCollectionValueTypes(): array
    {
        $internalType = self::unwrapNullableType($this->internalType);

        if ($internalType instanceof CollectionType) {
            return self::convertTypeToLegacyTypes($internalType->getCollectionValueType()) ?? [];
        }

        if ($internalType instanceof GenericType) {
            return self::convertTypeToLegacyTypes($internalType->getVariableTypes()[1]) ?? [];
        }

        return [];
    }

    private function validateCollectionArgument(array|self|null $collectionArgument, int $argumentIndex, string $argumentName): ?array
    {
        if (null === $collectionArgument) {
            return null;
        }

        if (!\is_array($collectionArgument)) {
            $collectionArgument = [$collectionArgument];
        }

        foreach ($collectionArgument as $type) {
            if (!$type instanceof self) {
                throw new \TypeError(sprintf('"%s()": Argument #%d (%s) must be of type "%s[]", "%s" or "null", array value "%s" given.', __METHOD__, $argumentIndex, $argumentName, self::class, self::class, get_debug_type($collectionArgument)));
            }
        }

        return $collectionArgument;
    }

    /**
     * Recursive method that converts a Type to its related {@see TypeInfoType}.
     */
    private static function convertLegacyTypeToType(self $legacyType): TypeInfoType
    {
        return self::createTypeFromLegacyValues(
            $legacyType->getBuiltinType(),
            $legacyType->isNullable(),
            $legacyType->getClassName(),
            $legacyType->isCollection(),
            $legacyType->getCollectionKeyTypes(),
            $legacyType->getCollectionValueTypes(),
        );
    }

    /**
     * @param list<self> $collectionKeyTypes
     * @param list<self> $collectionValueTypes
     */
    private static function createTypeFromLegacyValues(string $builtinType, bool $nullable, ?string $class, bool $collection, array $collectionKeyTypes, array $collectionValueTypes): TypeInfoType
    {
        $variableTypes = [];

        if ($collectionKeyTypes) {
            $collectionKeyTypes = array_unique(array_map(self::convertLegacyTypeToType(...), $collectionKeyTypes));
            $variableTypes[] = \count($collectionKeyTypes) > 1 ? TypeInfoType::union(...$collectionKeyTypes) : $collectionKeyTypes[0];
        }

        if ($collectionValueTypes) {
            if (!$collectionKeyTypes) {
                $variableTypes[] = [] === $collectionKeyTypes ? TypeInfoType::mixed() : TypeInfoType::union(TypeInfoType::int(), TypeInfoType::string());
            }

            $collectionValueTypes = array_unique(array_map(self::convertLegacyTypeToType(...), $collectionValueTypes));
            $variableTypes[] = \count($collectionValueTypes) > 1 ? TypeInfoType::union(...$collectionValueTypes) : $collectionValueTypes[0];
        }

        if ($collectionKeyTypes && !$collectionValueTypes) {
            $variableTypes[] = TypeInfoType::mixed();
        }

        try {
            $type = null !== $class ? TypeInfoType::object($class) : TypeInfoType::builtin(TypeIdentifier::from($builtinType));
        } catch (\ValueError) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid PHP type.', $builtinType));
        }

        if (\count($variableTypes)) {
            $type = TypeInfoType::generic($type, ...$variableTypes);
        }

        if ($collection) {
            $type = TypeInfoType::collection($type);
        }

        if ($nullable && !$type->isA(TypeIdentifier::MIXED)) {
            $type = TypeInfoType::nullable($type);
        }

        return $type;
    }

    /**
     * Converts a {@see TypeInfoType} to what is should have been in the "symfony/property-info" component.
     *
     * @return list<self>|null
     */
    private static function convertTypeToLegacyTypes(?TypeInfoType $type, bool $keepNullType = true): ?array
    {
        if (null === $type) {
            return null;
        }

        try {
            $typeIdentifier = $type->getBaseType()->getTypeIdentifier();
        } catch (\LogicException) {
            $typeIdentifier = null;
        }

        if (\in_array($typeIdentifier, [TypeIdentifier::MIXED, TypeIdentifier::NEVER, true])) {
            return null;
        }

        if (TypeIdentifier::NULL === $typeIdentifier) {
            return $keepNullType ? [new self(self::BUILTIN_TYPE_NULL)] : null;
        }

        if (TypeIdentifier::VOID === $typeIdentifier) {
            return [new self(self::BUILTIN_TYPE_NULL)];
        }

        try {
            $legacyType = self::convertTypeToLegacy($type);
        } catch (\LogicException) {
            return null;
        }

        if (!\is_array($legacyType)) {
            $legacyType = [$legacyType];
        }

        return $legacyType;
    }

    /**
     * Recursive method that converts {@see TypeInfoType} to its related Type (or list of Type).
     */
    private static function convertTypeToLegacy(TypeInfoType $type): self|array
    {
        if ($type instanceof UnionType) {
            $nullable = $type->isNullable();

            $unionTypes = [];
            foreach ($type->getTypes() as $unionType) {
                if ('null' === (string) $unionType) {
                    continue;
                }

                if ($unionType instanceof IntersectionType) {
                    throw new \LogicException(sprintf('DNF types are not supported by "%s".', self::class));
                }

                $unionTypes[] = $unionType;
            }

            /** @var list<self> $legacyTypes */
            $legacyTypes = array_map(self::convertTypeToLegacy(...), $unionTypes);

            if (1 === \count($legacyTypes)) {
                return $legacyTypes[0];
            }

            return $legacyTypes;
        }

        if ($type instanceof IntersectionType) {
            foreach ($type->getTypes() as $intersectionType) {
                if ($intersectionType instanceof UnionType) {
                    throw new \LogicException(sprintf('DNF types are not supported by "%s".', self::class));
                }
            }

            /** @var list<self> $legacyTypes */
            $legacyTypes = array_map(self::convertTypeToLegacy(...), $type->getTypes());

            if (1 === \count($legacyTypes)) {
                return $legacyTypes[0];
            }

            return $legacyTypes;
        }

        if ($type instanceof CollectionType) {
            return self::convertTypeToLegacy($type->getType());
        }

        $typeIdentifier = TypeIdentifier::MIXED;
        $className = null;
        $collectionKeyType = $collectionValueType = null;

        if ($type instanceof ObjectType) {
            $typeIdentifier = $type->getTypeIdentifier();
            $className = $type->getClassName();
        }

        if ($type instanceof GenericType) {
            $nestedType = self::unwrapNullableType($type->getType());

            if ($nestedType instanceof BuiltinType) {
                $typeIdentifier = $nestedType->getTypeIdentifier();
            } elseif ($nestedType instanceof ObjectType) {
                $typeIdentifier = $nestedType->getTypeIdentifier();
                $className = $nestedType->getClassName();
            }

            $variableTypes = $type->getVariableTypes();

            if (2 === \count($variableTypes)) {
                $collectionKeyType = self::convertTypeToLegacy($variableTypes[0]);
                $collectionValueType = self::convertTypeToLegacy($variableTypes[1]);
            } elseif (1 === \count($variableTypes)) {
                $collectionValueType = self::convertTypeToLegacy($variableTypes[0]);
            }
        }

        if ($type instanceof BuiltinType) {
            $typeIdentifier = $type->getTypeIdentifier();
        }

        return new self(
            builtinType: $typeIdentifier->value,
            nullable: $type->isNullable(),
            class: $className,
            collection: $type instanceof GenericType, // legacy generic is always considered as a collection
            collectionKeyType: $collectionKeyType,
            collectionValueType: $collectionValueType,
        );
    }

    private static function unwrapNullableType(TypeInfoType $type): TypeInfoType
    {
        if (!$type instanceof UnionType) {
            return $type;
        }

        if ($type->isA(TypeIdentifier::MIXED)) {
            return TypeInfoType::mixed();
        }

        return $type->asNonNullable();
    }
}
