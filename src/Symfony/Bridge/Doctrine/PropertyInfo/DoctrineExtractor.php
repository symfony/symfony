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
use Symfony\Component\PropertyInfo\Type;

/**
 * Extracts data using Doctrine ORM and ODM metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties(string $class, array $context = [])
    {
        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        $properties = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if ($metadata instanceof ClassMetadata && $metadata->embeddedClasses) {
            $properties = array_filter($properties, function ($property) {
                return !str_contains($property, '.');
            });

            $properties = array_merge($properties, array_keys($metadata->embeddedClasses));
        }

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes(string $class, string $property, array $context = [])
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

                return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $class)];
            }

            $collectionKeyType = Type::BUILTIN_TYPE_INT;

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

                    if (!$collectionKeyType = $this->getPhpType($typeOfField)) {
                        return null;
                    }
                }
            }

            return [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                Collection::class,
                true,
                new Type($collectionKeyType),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, $class)
            )];
        }

        if ($metadata instanceof ClassMetadata && isset($metadata->embeddedClasses[$property])) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, self::getMappingValue($metadata->embeddedClasses[$property], 'class'))];
        }

        if ($metadata->hasField($property)) {
            $typeOfField = $metadata->getTypeOfField($property);

            if (!$builtinType = $this->getPhpType($typeOfField)) {
                return null;
            }

            $nullable = $metadata instanceof ClassMetadata && $metadata->isNullable($property);

            // DBAL 4 has a special fallback strategy for BINGINT (int -> string)
            if (Types::BIGINT === $typeOfField && !method_exists(BigIntType::class, 'getName')) {
                return [
                    new Type(Type::BUILTIN_TYPE_INT, $nullable),
                    new Type(Type::BUILTIN_TYPE_STRING, $nullable),
                ];
            }

            $enumType = null;
            if (null !== $enumClass = self::getMappingValue($metadata->getFieldMapping($property), 'enumType') ?? null) {
                $enumType = new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $enumClass);
            }

            switch ($builtinType) {
                case Type::BUILTIN_TYPE_OBJECT:
                    switch ($typeOfField) {
                        case Types::DATE_MUTABLE:
                        case Types::DATETIME_MUTABLE:
                        case Types::DATETIMETZ_MUTABLE:
                        case 'vardatetime':
                        case Types::TIME_MUTABLE:
                            return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateTime')];

                        case Types::DATE_IMMUTABLE:
                        case Types::DATETIME_IMMUTABLE:
                        case Types::DATETIMETZ_IMMUTABLE:
                        case Types::TIME_IMMUTABLE:
                            return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateTimeImmutable')];

                        case Types::DATEINTERVAL:
                            return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateInterval')];
                    }

                    break;
                case Type::BUILTIN_TYPE_ARRAY:
                    switch ($typeOfField) {
                        case 'array':      // DBAL < 4
                        case 'json_array': // DBAL < 3
                            // return null if $enumType is set, because we can't determine if collectionKeyType is string or int
                            if ($enumType) {
                                return null;
                            }

                            return [new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true)];

                        case Types::SIMPLE_ARRAY:
                            return [new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, new Type(Type::BUILTIN_TYPE_INT), $enumType ?? new Type(Type::BUILTIN_TYPE_STRING))];
                    }
                    break;
                case Type::BUILTIN_TYPE_INT:
                case Type::BUILTIN_TYPE_STRING:
                    if ($enumType) {
                        return [$enumType];
                    }
                    break;
            }

            return [new Type($builtinType, $nullable)];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(string $class, string $property, array $context = [])
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(string $class, string $property, array $context = [])
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
        } catch (MappingException|OrmMappingException $exception) {
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
    private function isAssociationNullable($associationMapping): bool
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
    private function getPhpType(string $doctrineType): ?string
    {
        switch ($doctrineType) {
            case Types::SMALLINT:
            case Types::INTEGER:
                return Type::BUILTIN_TYPE_INT;

            case Types::FLOAT:
                return Type::BUILTIN_TYPE_FLOAT;

            case Types::BIGINT:
            case Types::STRING:
            case Types::TEXT:
            case Types::GUID:
            case Types::DECIMAL:
                return Type::BUILTIN_TYPE_STRING;

            case Types::BOOLEAN:
                return Type::BUILTIN_TYPE_BOOL;

            case Types::BLOB:
            case Types::BINARY:
                return Type::BUILTIN_TYPE_RESOURCE;

            case 'object': // DBAL < 4
            case Types::DATE_MUTABLE:
            case Types::DATETIME_MUTABLE:
            case Types::DATETIMETZ_MUTABLE:
            case 'vardatetime':
            case Types::TIME_MUTABLE:
            case Types::DATE_IMMUTABLE:
            case Types::DATETIME_IMMUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
            case Types::TIME_IMMUTABLE:
            case Types::DATEINTERVAL:
                return Type::BUILTIN_TYPE_OBJECT;

            case 'array': // DBAL < 4
            case Types::SIMPLE_ARRAY:
            case 'json_array': // DBAL < 3
                return Type::BUILTIN_TYPE_ARRAY;
        }

        return null;
    }

    /**
     * @param array|AssociationMapping|EmbeddedClassMapping|FieldMapping|JoinColumnMapping $mapping
     *
     * @return mixed
     */
    private static function getMappingValue($mapping, string $key)
    {
        if ($mapping instanceof AssociationMapping || $mapping instanceof EmbeddedClassMapping || $mapping instanceof FieldMapping || $mapping instanceof JoinColumnMapping) {
            return $mapping->$key ?? null;
        }

        return $mapping[$key] ?? null;
    }
}
