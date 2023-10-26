<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * A helper about PropertyInfo Type conversion.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class BackwardCompatibilityHelper
{
    /**
     * Converts a {@see Type} to what is should have been in the "symfony/property-info" component.
     *
     * @return list<LegacyType>|null
     */
    public static function convertTypeToLegacyTypes(?Type $type, bool $keepNullType = true): ?array
    {
        if (null === $type) {
            return null;
        }

        try {
            $typeIdentifier = $type->getBaseType()->getTypeIdentifier();
        } catch (LogicException) {
            $typeIdentifier = null;
        }

        if (\in_array($typeIdentifier, [TypeIdentifier::MIXED, TypeIdentifier::NEVER, true])) {
            return null;
        }

        if (TypeIdentifier::NULL === $typeIdentifier) {
            return $keepNullType ? [new LegacyType('null')] : null;
        }

        if (TypeIdentifier::VOID === $typeIdentifier) {
            return [new LegacyType('null')];
        }

        try {
            $legacyType = self::convertTypeToLegacy($type);
        } catch (LogicException) {
            return null;
        }

        if (!\is_array($legacyType)) {
            $legacyType = [$legacyType];
        }

        return $legacyType;
    }

    /**
     * Converts a {@see LegacyType} to what is should have been in the "symfony/type-info" component.
     *
     * @param list<LegacyType>|null $types
     */
    public static function convertLegacyTypesToType(?array $legacyTypes): ?Type
    {
        if (!$legacyTypes) {
            return null;
        }

        $types = [];
        $nullable = false;

        foreach (array_map(self::convertLegacyTypeToType(...), $legacyTypes) as $type) {
            if ($type->isNullable()) {
                $nullable = true;

                if ($type instanceof BuiltinType && TypeIdentifier::NULL === $type->getTypeIdentifier()) {
                    continue;
                }

                $type = self::unwrapNullableType($type);
            }

            if ($type instanceof UnionType) {
                $types = [$types, ...$type->getTypes()];

                continue;
            }

            $types[] = $type;
        }

        if ($nullable && !$types) {
            return Type::null();
        }

        $type = \count($types) > 1 ? Type::union(...$types) : $types[0];
        if ($nullable) {
            $type = Type::nullable($type);
        }

        return $type;
    }

    /**
     * @param list<LegacyType> $collectionKeyTypes
     * @param list<LegacyType> $collectionValueTypes
     */
    public static function createTypeFromLegacyValues(string $builtinType, bool $nullable, ?string $class, bool $collection, array $collectionKeyTypes, array $collectionValueTypes): Type
    {
        $variableTypes = [];

        if ($collectionKeyTypes) {
            $collectionKeyTypes = array_unique(array_map(self::convertLegacyTypeToType(...), $collectionKeyTypes));
            $variableTypes[] = \count($collectionKeyTypes) > 1 ? Type::union(...$collectionKeyTypes) : $collectionKeyTypes[0];
        }

        if ($collectionValueTypes) {
            if (!$collectionKeyTypes) {
                $variableTypes[] = [] === $collectionKeyTypes ? Type::mixed() : Type::union(Type::int(), Type::string());
            }

            $collectionValueTypes = array_unique(array_map(self::convertLegacyTypeToType(...), $collectionValueTypes));
            $variableTypes[] = \count($collectionValueTypes) > 1 ? Type::union(...$collectionValueTypes) : $collectionValueTypes[0];
        }

        if ($collectionKeyTypes && !$collectionValueTypes) {
            $variableTypes[] = Type::mixed();
        }

        try {
            $type = null !== $class ? Type::object($class) : Type::builtin(TypeIdentifier::from($builtinType));
        } catch (\ValueError) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid PHP type.', $builtinType));
        }

        if (\count($variableTypes)) {
            $type = Type::generic($type, ...$variableTypes);
        }

        if ($collection) {
            $type = Type::collection($type);
        }

        if ($nullable && !$type->isNullable) {
            $type = Type::nullable($type);
        }

        $type->setCollection($collection);

        return $type;
    }

    public static function unwrapNullableType(Type $type): Type
    {
        if (!$type instanceof UnionType) {
            return $type;
        }

        return $type->asNonNullable();
    }

    /**
     * Recursive method that converts {@see Type} to its related {@see LegacyType} (or list of {@see @LegacyType}).
     *
     * @return LegacyType|list<LegacyType>
     */
    private static function convertTypeToLegacy(Type $type): LegacyType|array
    {
        if ($type instanceof UnionType) {
            $nullable = $type->isNullable();

            $unionTypes = [];
            foreach ($type->getTypes() as $unionType) {
                if ('null' === (string) $unionType) {
                    continue;
                }

                if ($unionType instanceof IntersectionType) {
                    throw new LogicException(sprintf('DNF types are not supported by "%s".', LegacyType::class));
                }

                $unionType->setNullable($nullable);
                $unionTypes[] = $unionType;
            }

            /** @var list<LegacyType> $legacyTypes */
            $legacyTypes = array_map(self::convertTypeToLegacy(...), $unionTypes);

            if (1 === \count($legacyTypes)) {
                return $legacyTypes[0];
            }

            return $legacyTypes;
        }

        if ($type instanceof IntersectionType) {
            foreach ($type->getTypes() as $intersectionType) {
                if ($intersectionType instanceof UnionType) {
                    throw new LogicException(sprintf('DNF types are not supported by "%s".', LegacyType::class));
                }
            }

            /** @var list<LegacyType> $legacyTypes */
            $legacyTypes = array_map(self::convertTypeToLegacy(...), $type->getTypes());

            if (1 === \count($legacyTypes)) {
                return $legacyTypes[0];
            }

            return $legacyTypes;
        }

        if ($type instanceof CollectionType) {
            $nestedType = $type->getType();
            $nestedType->setCollection(true);

            return self::convertTypeToLegacy($nestedType);
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

        return new LegacyType(
            builtinType: $typeIdentifier->value,
            nullable: $type->isNullable(),
            class: $className,
            collection: $type instanceof GenericType || $type->isCollection, // legacy generic is always considered as a collection
            collectionKeyType: $collectionKeyType,
            collectionValueType: $collectionValueType,
        );
    }

    /**
     * Recursive method that converts {@see LegacyType} to its related {@see Type}.
     */
    private static function convertLegacyTypeToType(LegacyType $legacyType): Type
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
}
