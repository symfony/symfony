<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Util;

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

/**
 * @internal
 */
class LegacyTypeConverter
{
    /**
     * @param LegacyType[]|null $legacyTypes
     */
    public static function toTypeInfoType(?array $legacyTypes): ?Type
    {
        if (null === $legacyTypes || [] === $legacyTypes) {
            return null;
        }

        $nullable = false;
        $types = [];

        foreach ($legacyTypes as $legacyType) {
            switch ($legacyType->getBuiltinType()) {
                case LegacyType::BUILTIN_TYPE_ARRAY:
                    $typeInfoType = Type::array(self::toTypeInfoType($legacyType->getCollectionValueTypes()), self::toTypeInfoType($legacyType->getCollectionKeyTypes()));
                    break;
                case LegacyType::BUILTIN_TYPE_BOOL:
                    $typeInfoType = Type::bool();
                    break;
                case LegacyType::BUILTIN_TYPE_CALLABLE:
                    $typeInfoType = Type::callable();
                    break;
                case LegacyType::BUILTIN_TYPE_FALSE:
                    $typeInfoType = Type::false();
                    break;
                case LegacyType::BUILTIN_TYPE_FLOAT:
                    $typeInfoType = Type::float();
                    break;
                case LegacyType::BUILTIN_TYPE_INT:
                    $typeInfoType = Type::int();
                    break;
                case LegacyType::BUILTIN_TYPE_ITERABLE:
                    $typeInfoType = Type::iterable(self::toTypeInfoType($legacyType->getCollectionValueTypes()), self::toTypeInfoType($legacyType->getCollectionKeyTypes()));
                    break;
                case LegacyType::BUILTIN_TYPE_OBJECT:
                    if ($legacyType->isCollection()) {
                        $typeInfoType = Type::collection(Type::object($legacyType->getClassName()), self::toTypeInfoType($legacyType->getCollectionValueTypes()), self::toTypeInfoType($legacyType->getCollectionKeyTypes()));
                    } else {
                        $typeInfoType = Type::object($legacyType->getClassName());
                    }

                    break;
                case LegacyType::BUILTIN_TYPE_RESOURCE:
                    $typeInfoType = Type::resource();
                    break;
                case LegacyType::BUILTIN_TYPE_STRING:
                    $typeInfoType = Type::string();
                    break;
                case LegacyType::BUILTIN_TYPE_TRUE:
                    $typeInfoType = Type::true();
                    break;
                default:
                    $typeInfoType = null;
                    break;
            }

            if (LegacyType::BUILTIN_TYPE_NULL === $legacyType->getBuiltinType() || $legacyType->isNullable()) {
                $nullable = true;
            }

            if (null !== $typeInfoType) {
                $types[] = $typeInfoType;
            }
        }

        if (1 === \count($types)) {
            return $nullable ? Type::nullable($types[0]) : $types[0];
        }

        return $nullable ? Type::nullable(Type::union(...$types)) : Type::union(...$types);
    }
}
