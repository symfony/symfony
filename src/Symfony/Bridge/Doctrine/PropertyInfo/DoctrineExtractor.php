<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\PropertyInfo;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\EmbeddedClassMapping;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\MappingException as OrmMappingException;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Extracts data using Doctrine ORM and ODM metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        $properties = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if ($metadata instanceof ClassMetadata && $metadata->embeddedClasses) {
            $properties = array_filter($properties, fn ($property) => !str_contains($property, '.'));

            $properties = array_merge($properties, array_keys($metadata->embeddedClasses));
        }

        return $properties;
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        if ($metadata->hasAssociation($property)) {
            $class = $metadata->getAssociationTargetClass($property);

            if ($metadata->isSingleValuedAssociation($property)) {
                if ($metadata instanceof ClassMetadata) {
                    $associationMapping = $metadata->getAssociationMapping($property);
                    $nullable = $this->isAssociationNullable($associationMapping);
                } else {
                    $nullable = false;
                }

                return $nullable ? Type::nullable(Type::object($class)) : Type::object($class);
            }

            $collectionKeyType = TypeIdentifier::INT;

            if ($metadata instanceof ClassMetadata) {
                $associationMapping = $metadata->getAssociationMapping($property);

                if (self::getMappingValue($associationMapping, 'indexBy')) {
                    $subMetadata = $this->entityManager->getClassMetadata(self::getMappingValue($associationMapping, 'targetEntity'));

                    // Check if indexBy value is a property
                    $fieldName = self::getMappingValue($associationMapping, 'indexBy');
                    if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                        $fieldName = $subMetadata->getFieldForColumn(self::getMappingValue($associationMapping, 'indexBy'));
                        // Not a property, maybe a column name?
                        if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                            // Maybe the column name is the association join column?
                            $associationMapping = $subMetadata->getAssociationMapping($fieldName);

                            $indexProperty = $subMetadata->getSingleAssociationReferencedJoinColumnName($fieldName);
                            $subMetadata = $this->entityManager->getClassMetadata(self::getMappingValue($associationMapping, 'targetEntity'));

                            // Not a property, maybe a column name?
                            if (null === ($typeOfField = $subMetadata->getTypeOfField($indexProperty))) {
                                $fieldName = $subMetadata->getFieldForColumn($indexProperty);
                                $typeOfField = $subMetadata->getTypeOfField($fieldName);
                            }
                        }
                    }

                    if (!$collectionKeyType = $this->getTypeIdentifier($typeOfField)) {
                        return null;
                    }
                }
            }

            return Type::collection(Type::object(Collection::class), Type::object($class), Type::builtin($collectionKeyType));
        }

        if ($metadata instanceof ClassMetadata && isset($metadata->embeddedClasses[$property])) {
            return Type::object(self::getMappingValue($metadata->embeddedClasses[$property], 'class'));
        }

        if (!$metadata->hasField($property)) {
            return null;
        }

        $typeOfField = $metadata->getTypeOfField($property);

        if (!$typeIdentifier = $this->getTypeIdentifier($typeOfField)) {
            return null;
        }

        $nullable = $metadata instanceof ClassMetadata && $metadata->isNullable($property);

        // DBAL 4 has a special fallback strategy for BINGINT (int -> string)
        if (Types::BIGINT === $typeOfField && !method_exists(BigIntType::class, 'getName')) {
            return $nullable ? Type::nullable(Type::union(Type::int(), Type::string())) : Type::union(Type::int(), Type::string());
        }

        $enumType = null;

        if (null !== $enumClass = self::getMappingValue($metadata->getFieldMapping($property), 'enumType') ?? null) {
            $enumType = $nullable ? Type::nullable(Type::enum($enumClass)) : Type::enum($enumClass);
        }

        $builtinType = $nullable ? Type::nullable(Type::builtin($typeIdentifier)) : Type::builtin($typeIdentifier);

        return match ($typeIdentifier) {
            TypeIdentifier::OBJECT => match ($typeOfField) {
                Types::DATE_MUTABLE, Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE, 'vardatetime', Types::TIME_MUTABLE => $nullable ? Type::nullable(Type::object(\DateTime::class)) : Type::object(\DateTime::class),
                Types::DATE_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE, Types::TIME_IMMUTABLE => $nullable ? Type::nullable(Type::object(\DateTimeImmutable::class)) : Type::object(\DateTimeImmutable::class),
                Types::DATEINTERVAL => $nullable ? Type::nullable(Type::object(\DateInterval::class)) : Type::object(\DateInterval::class),
                default => $builtinType,
            },
            TypeIdentifier::ARRAY => match ($typeOfField) {
                'array', 'json_array' => $enumType ? null : ($nullable ? Type::nullable(Type::array()) : Type::array()),
                Types::SIMPLE_ARRAY => $nullable ? Type::nullable(Type::list($enumType ?? Type::string())) : Type::list($enumType ?? Type::string()),
                default => $builtinType,
            },
            TypeIdentifier::INT, TypeIdentifier::STRING => $enumType ? $enumType : $builtinType,
            default => $builtinType,
        };
    }

    /**
     * @deprecated since Symfony 7.3, use "getType" instead
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        trigger_deprecation('symfony/property-info', '7.3', 'The "%s()" method is deprecated, use "%s::getType()" instead.', __METHOD__, self::class);

        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        if ($metadata->hasAssociation($property)) {
            $class = $metadata->getAssociationTargetClass($property);

            if ($metadata->isSingleValuedAssociation($property)) {
                if ($metadata instanceof ClassMetadata) {
                    $associationMapping = $metadata->getAssociationMapping($property);

                    $nullable = $this->isAssociationNullable($associationMapping);
                } else {
                    $nullable = false;
                }

                return [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, $class)];
            }

            $collectionKeyType = LegacyType::BUILTIN_TYPE_INT;

            if ($metadata instanceof ClassMetadata) {
                $associationMapping = $metadata->getAssociationMapping($property);

                if (self::getMappingValue($associationMapping, 'indexBy')) {
                    $subMetadata = $this->entityManager->getClassMetadata(self::getMappingValue($associationMapping, 'targetEntity'));

                    // Check if indexBy value is a property
                    $fieldName = self::getMappingValue($associationMapping, 'indexBy');
                    if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                        $fieldName = $subMetadata->getFieldForColumn(self::getMappingValue($associationMapping, 'indexBy'));
                        // Not a property, maybe a column name?
                        if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                            // Maybe the column name is the association join column?
                            $associationMapping = $subMetadata->getAssociationMapping($fieldName);

                            $indexProperty = $subMetadata->getSingleAssociationReferencedJoinColumnName($fieldName);
                            $subMetadata = $this->entityManager->getClassMetadata(self::getMappingValue($associationMapping, 'targetEntity'));

                            // Not a property, maybe a column name?
                            if (null === ($typeOfField = $subMetadata->getTypeOfField($indexProperty))) {
                                $fieldName = $subMetadata->getFieldForColumn($indexProperty);
                                $typeOfField = $subMetadata->getTypeOfField($fieldName);
                            }
                        }
                    }

                    if (!$collectionKeyType = $this->getTypeIdentifierLegacy($typeOfField)) {
                        return null;
                    }
                }
            }

            return [new LegacyType(
                LegacyType::BUILTIN_TYPE_OBJECT,
                false,
                Collection::class,
                true,
                new LegacyType($collectionKeyType),
                new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, $class)
            )];
        }

        if ($metadata instanceof ClassMetadata && isset($metadata->embeddedClasses[$property])) {
            return [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, self::getMappingValue($metadata->embeddedClasses[$property], 'class'))];
        }

        if ($metadata->hasField($property)) {
            $typeOfField = $metadata->getTypeOfField($property);

            if (!$builtinType = $this->getTypeIdentifierLegacy($typeOfField)) {
                return null;
            }

            $nullable = $metadata instanceof ClassMetadata && $metadata->isNullable($property);

            // DBAL 4 has a special fallback strategy for BINGINT (int -> string)
            if (Types::BIGINT === $typeOfField && !method_exists(BigIntType::class, 'getName')) {
                return [
                    new LegacyType(LegacyType::BUILTIN_TYPE_INT, $nullable),
                    new LegacyType(LegacyType::BUILTIN_TYPE_STRING, $nullable),
                ];
            }

            $enumType = null;
            if (null !== $enumClass = self::getMappingValue($metadata->getFieldMapping($property), 'enumType') ?? null) {
                $enumType = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, $enumClass);
            }

            switch ($builtinType) {
                case LegacyType::BUILTIN_TYPE_OBJECT:
                    switch ($typeOfField) {
                        case Types::DATE_MUTABLE:
                        case Types::DATETIME_MUTABLE:
                        case Types::DATETIMETZ_MUTABLE:
                        case 'vardatetime':
                        case Types::TIME_MUTABLE:
                            return [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, 'DateTime')];

                        case Types::DATE_IMMUTABLE:
                        case Types::DATETIME_IMMUTABLE:
                        case Types::DATETIMETZ_IMMUTABLE:
                        case Types::TIME_IMMUTABLE:
                            return [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, 'DateTimeImmutable')];

                        case Types::DATEINTERVAL:
                            return [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $nullable, 'DateInterval')];
                    }

                    break;
                case LegacyType::BUILTIN_TYPE_ARRAY:
                    switch ($typeOfField) {
                        case 'array':      // DBAL < 4
                        case 'json_array': // DBAL < 3
                            // return null if $enumType is set, because we can't determine if collectionKeyType is string or int
                            if ($enumType) {
                                return null;
                            }

                            return [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true)];

                        case Types::SIMPLE_ARRAY:
                            return [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $enumType ?? new LegacyType(LegacyType::BUILTIN_TYPE_STRING))];
                    }
                    break;
                case LegacyType::BUILTIN_TYPE_INT:
                case LegacyType::BUILTIN_TYPE_STRING:
                    if ($enumType) {
                        return [$enumType];
                    }
                    break;
            }

            return [new LegacyType($builtinType, $nullable)];
        }

        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        if (
            null === ($metadata = $this->getMetadata($class))
            || ClassMetadata::GENERATOR_TYPE_NONE === $metadata->generatorType
            || !\in_array($property, $metadata->getIdentifierFieldNames(), true)
        ) {
            return null;
        }

        return false;
    }

    private function getMetadata(string $class): ?ClassMetadata
    {
        try {
            return $this->entityManager->getClassMetadata($class);
        } catch (MappingException|OrmMappingException) {
            return null;
        }
    }

    /**
     * Determines whether an association is nullable.
     *
     * @param array<string, mixed>|AssociationMapping $associationMapping
     *
     * @see https://github.com/doctrine/doctrine2/blob/v2.5.4/lib/Doctrine/ORM/Tools/EntityGenerator.php#L1221-L1246
     */
    private function isAssociationNullable(array|AssociationMapping $associationMapping): bool
    {
        if (self::getMappingValue($associationMapping, 'id')) {
            return false;
        }

        if (!self::getMappingValue($associationMapping, 'joinColumns')) {
            return true;
        }

        $joinColumns = self::getMappingValue($associationMapping, 'joinColumns');
        foreach ($joinColumns as $joinColumn) {
            if (false === self::getMappingValue($joinColumn, 'nullable')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the corresponding built-in PHP type.
     */
    private function getTypeIdentifier(string $doctrineType): ?TypeIdentifier
    {
        return match ($doctrineType) {
            Types::SMALLINT,
            Types::INTEGER => TypeIdentifier::INT,
            Types::FLOAT => TypeIdentifier::FLOAT,
            Types::BIGINT,
            Types::STRING,
            Types::TEXT,
            Types::GUID,
            Types::DECIMAL => TypeIdentifier::STRING,
            Types::BOOLEAN => TypeIdentifier::BOOL,
            Types::BLOB,
            Types::BINARY => TypeIdentifier::RESOURCE,
            'object', // DBAL < 4
            Types::DATE_MUTABLE,
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            'vardatetime',
            Types::TIME_MUTABLE,
            Types::DATE_IMMUTABLE,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
            Types::TIME_IMMUTABLE,
            Types::DATEINTERVAL => TypeIdentifier::OBJECT,
            'array', // DBAL < 4
            'json_array', // DBAL < 3
            Types::SIMPLE_ARRAY => TypeIdentifier::ARRAY,
            default => null,
        };
    }

    private function getTypeIdentifierLegacy(string $doctrineType): ?string
    {
        return match ($doctrineType) {
            Types::SMALLINT,
            Types::INTEGER => LegacyType::BUILTIN_TYPE_INT,
            Types::FLOAT => LegacyType::BUILTIN_TYPE_FLOAT,
            Types::BIGINT,
            Types::STRING,
            Types::TEXT,
            Types::GUID,
            Types::DECIMAL => LegacyType::BUILTIN_TYPE_STRING,
            Types::BOOLEAN => LegacyType::BUILTIN_TYPE_BOOL,
            Types::BLOB,
            Types::BINARY => LegacyType::BUILTIN_TYPE_RESOURCE,
            'object', // DBAL < 4
            Types::DATE_MUTABLE,
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            'vardatetime',
            Types::TIME_MUTABLE,
            Types::DATE_IMMUTABLE,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
            Types::TIME_IMMUTABLE,
            Types::DATEINTERVAL => LegacyType::BUILTIN_TYPE_OBJECT,
            'array', // DBAL < 4
            'json_array', // DBAL < 3
            Types::SIMPLE_ARRAY => LegacyType::BUILTIN_TYPE_ARRAY,
            default => null,
        };
    }

    private static function getMappingValue(array|AssociationMapping|EmbeddedClassMapping|FieldMapping|JoinColumnMapping $mapping, string $key): mixed
    {
        if ($mapping instanceof AssociationMapping || $mapping instanceof EmbeddedClassMapping || $mapping instanceof FieldMapping || $mapping instanceof JoinColumnMapping) {
            return $mapping->$key ?? null;
        }

        return $mapping[$key] ?? null;
    }
}
