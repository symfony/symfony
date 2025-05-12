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

use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Helper trait to create any type easily.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
trait TypeFactoryTrait
{
    /**
     * @template T of TypeIdentifier
     * @template U value-of<T>
     *
     * @param T|U $identifier
     *
     * @return BuiltinType<T>
     */
    public static function builtin(TypeIdentifier|string $identifier): BuiltinType
    {
        /** @var T $identifier */
        $identifier = \is_string($identifier) ? TypeIdentifier::from($identifier) : $identifier;

        return new BuiltinType($identifier);
    }

    /**
     * @return BuiltinType<TypeIdentifier::INT>
     */
    public static function int(): BuiltinType
    {
        return self::builtin(TypeIdentifier::INT);
    }

    /**
     * @return BuiltinType<TypeIdentifier::FLOAT>
     */
    public static function float(): BuiltinType
    {
        return self::builtin(TypeIdentifier::FLOAT);
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function string(): BuiltinType
    {
        return self::builtin(TypeIdentifier::STRING);
    }

    /**
     * @return BuiltinType<TypeIdentifier::BOOL>
     */
    public static function bool(): BuiltinType
    {
        return self::builtin(TypeIdentifier::BOOL);
    }

    /**
     * @return BuiltinType<TypeIdentifier::RESOURCE>
     */
    public static function resource(): BuiltinType
    {
        return self::builtin(TypeIdentifier::RESOURCE);
    }

    /**
     * @return BuiltinType<TypeIdentifier::FALSE>
     */
    public static function false(): BuiltinType
    {
        return self::builtin(TypeIdentifier::FALSE);
    }

    /**
     * @return BuiltinType<TypeIdentifier::TRUE>
     */
    public static function true(): BuiltinType
    {
        return self::builtin(TypeIdentifier::TRUE);
    }

    /**
     * @return BuiltinType<TypeIdentifier::CALLABLE>
     */
    public static function callable(): BuiltinType
    {
        return self::builtin(TypeIdentifier::CALLABLE);
    }

    /**
     * @return BuiltinType<TypeIdentifier::MIXED>
     */
    public static function mixed(): BuiltinType
    {
        return self::builtin(TypeIdentifier::MIXED);
    }

    /**
     * @return BuiltinType<TypeIdentifier::NULL>
     */
    public static function null(): BuiltinType
    {
        return self::builtin(TypeIdentifier::NULL);
    }

    /**
     * @return BuiltinType<TypeIdentifier::VOID>
     */
    public static function void(): BuiltinType
    {
        return self::builtin(TypeIdentifier::VOID);
    }

    /**
     * @return BuiltinType<TypeIdentifier::NEVER>
     */
    public static function never(): BuiltinType
    {
        return self::builtin(TypeIdentifier::NEVER);
    }

    /**
     * @template T of BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType|GenericType
     *
     * @param T $type
     *
     * @return CollectionType<T>
     */
    public static function collection(BuiltinType|ObjectType|GenericType $type, ?Type $value = null, ?Type $key = null, bool $asList = false): CollectionType
    {
        if (!$type instanceof GenericType && (null !== $value || null !== $key)) {
            $type = self::generic($type, $key ?? self::arrayKey(), $value ?? self::mixed());
        }

        return new CollectionType($type, $asList);
    }

    /**
     * @return CollectionType<BuiltinType<TypeIdentifier::ARRAY>>
     */
    public static function array(?Type $value = null, ?Type $key = null, bool $asList = false): CollectionType
    {
        return self::collection(self::builtin(TypeIdentifier::ARRAY), $value, $key, $asList);
    }

    /**
     * @return CollectionType<BuiltinType<TypeIdentifier::ITERABLE>>
     */
    public static function iterable(?Type $value = null, ?Type $key = null, bool $asList = false): CollectionType
    {
        if ($asList) {
            trigger_deprecation('symfony/type-info', '7.3', 'The third argument of "%s()" is deprecated. Use the "%s::list()" method to create a list instead.', __METHOD__, self::class);
        }

        return self::collection(self::builtin(TypeIdentifier::ITERABLE), $value, $key, $asList);
    }

    /**
     * @return CollectionType<BuiltinType<TypeIdentifier::ARRAY>>
     */
    public static function list(?Type $value = null): CollectionType
    {
        return self::array($value, self::int(), asList: true);
    }

    /**
     * @return CollectionType<BuiltinType<TypeIdentifier::ARRAY>>
     */
    public static function dict(?Type $value = null): CollectionType
    {
        return self::array($value, self::string());
    }

    /**
     * @param array<array{type: Type, optional?: bool}|Type> $shape
     */
    public static function arrayShape(array $shape, bool $sealed = true, ?Type $extraKeyType = null, ?Type $extraValueType = null): ArrayShapeType
    {
        $shape = array_map(static function (array|Type $item): array {
            return $item instanceof Type
                ? ['type' => $item, 'optional' => false]
                : ['type' => $item['type'], 'optional' => $item['optional'] ?? false];
        }, $shape);

        if ($extraKeyType || $extraValueType) {
            $sealed = false;
        }

        $extraKeyType ??= !$sealed ? Type::arrayKey() : null;
        $extraValueType ??= !$sealed ? Type::mixed() : null;

        return new ArrayShapeType($shape, $extraKeyType, $extraValueType);
    }

    public static function arrayKey(): UnionType
    {
        return self::union(self::int(), self::string());
    }

    /**
     * @template T of class-string
     *
     * @param T|null $className
     *
     * @return ($className is class-string ? ObjectType<T> : BuiltinType<TypeIdentifier::OBJECT>)
     */
    public static function object(?string $className = null): BuiltinType|ObjectType
    {
        return null !== $className ? new ObjectType($className) : new BuiltinType(TypeIdentifier::OBJECT);
    }

    /**
     * @template T of class-string<\UnitEnum>|class-string<\BackedEnum>
     * @template U of BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::STRING>
     *
     * @param T      $className
     * @param U|null $backingType
     *
     * @return ($className is class-string<\BackedEnum> ? ($backingType is U ? BackedEnumType<T,U> : BackedEnumType<T,BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::STRING>>) : EnumType<T>))
     */
    public static function enum(string $className, ?BuiltinType $backingType = null): EnumType
    {
        if (is_subclass_of($className, \BackedEnum::class)) {
            if (null === $backingType) {
                $reflectionBackingType = (new \ReflectionEnum($className))->getBackingType();
                $typeIdentifier = TypeIdentifier::INT->value === (string) $reflectionBackingType ? TypeIdentifier::INT : TypeIdentifier::STRING;
                $backingType = new BuiltinType($typeIdentifier);
            }

            return new BackedEnumType($className, $backingType);
        }

        return new EnumType($className);
    }

    /**
     * @template T of BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType
     *
     * @param T $mainType
     *
     * @return GenericType<T>
     */
    public static function generic(BuiltinType|ObjectType $mainType, Type ...$variableTypes): GenericType
    {
        return new GenericType($mainType, ...$variableTypes);
    }

    /**
     * @template T of Type
     *
     * @param T|null $bound
     *
     * @return ($bound is null ? TemplateType<BuiltinType<TypeIdentifier::MIXED>> : TemplateType<T>)
     */
    public static function template(string $name, ?Type $bound = null): TemplateType
    {
        return new TemplateType($name, $bound ?? Type::mixed());
    }

    /**
     * @template T of Type
     *
     * @param list<T> $types
     *
     * @return UnionType<T>|NullableType<T>
     */
    public static function union(Type ...$types): UnionType
    {
        /** @var list<T> $unionTypes */
        $unionTypes = [];

        $nullableUnion = false;
        $isNullable = fn (Type $type): bool => $type instanceof BuiltinType && TypeIdentifier::NULL === $type->getTypeIdentifier();

        foreach ($types as $type) {
            if ($type instanceof NullableType) {
                $nullableUnion = true;
                $unionTypes[] = $type->getWrappedType();

                continue;
            }

            if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $unionType) {
                    if ($isNullable($type)) {
                        $nullableUnion = true;

                        continue;
                    }

                    $unionTypes[] = $unionType;
                }

                continue;
            }

            if ($isNullable($type)) {
                $nullableUnion = true;

                continue;
            }

            $unionTypes[] = $type;
        }

        if (1 === \count($unionTypes)) {
            return self::nullable($unionTypes[0]);
        }

        $unionType = new UnionType(...$unionTypes);

        return $nullableUnion ? self::nullable($unionType) : $unionType;
    }

    /**
     * @template T of ObjectType|GenericType<ObjectType>|CollectionType<GenericType<ObjectType>>
     *
     * @param list<T|IntersectionType<T>> $types
     *
     * @return IntersectionType<T>
     */
    public static function intersection(Type ...$types): IntersectionType
    {
        /** @var list<T> $intersectionTypes */
        $intersectionTypes = [];

        foreach ($types as $type) {
            if (!$type instanceof IntersectionType) {
                $intersectionTypes[] = $type;

                continue;
            }

            foreach ($type->getTypes() as $intersectionType) {
                $intersectionTypes[] = $intersectionType;
            }
        }

        return new IntersectionType(...$intersectionTypes);
    }

    /**
     * @template T of Type
     *
     * @param T $type
     *
     * @return T|NullableType<T>
     */
    public static function nullable(Type $type): Type
    {
        if ($type->isNullable()) {
            return $type;
        }

        return new NullableType($type);
    }

    public static function fromValue(mixed $value): Type
    {
        $type = match ($value) {
            null => self::null(),
            true => self::true(),
            false => self::false(),
            default => null,
        };

        if (null !== $type) {
            return $type;
        }

        if (\is_callable($value)) {
            return Type::callable();
        }

        if (\is_resource($value)) {
            return Type::resource();
        }

        $type = match (get_debug_type($value)) {
            TypeIdentifier::INT->value => self::int(),
            TypeIdentifier::FLOAT->value => self::float(),
            TypeIdentifier::STRING->value => self::string(),
            default => null,
        };

        if (null !== $type) {
            return $type;
        }

        $type = match (true) {
            \is_object($value) => \stdClass::class === $value::class ? self::object() : self::object($value::class),
            \is_array($value) => self::builtin(TypeIdentifier::ARRAY),
            default => null,
        };

        if (null === $type) {
            return Type::mixed();
        }

        if (is_iterable($value)) {
            /** @var list<BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::STRING>> $keyTypes */
            $keyTypes = [];

            /** @var list<Type> $valueTypes */
            $valueTypes = [];

            $i = 0;

            foreach ($value as $k => $v) {
                $keyTypes[] = self::fromValue($k);
                $valueTypes[] = self::fromValue($v);
            }

            if ($keyTypes) {
                $keyTypes = array_values(array_unique($keyTypes));
                $keyType = \count($keyTypes) > 1 ? self::union(...$keyTypes) : $keyTypes[0];
            } else {
                $keyType = Type::arrayKey();
            }

            $valueType = $valueTypes ? CollectionType::mergeCollectionValueTypes($valueTypes) : Type::mixed();

            return self::collection($type, $valueType, $keyType, \is_array($value) && array_is_list($value));
        }

        if ($value instanceof \ArrayAccess) {
            return self::collection($type);
        }

        return $type;
    }
}
