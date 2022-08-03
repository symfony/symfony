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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Embedded;
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
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties(string $class, array $context = []): ?array
    {
        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        $properties = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if ($metadata instanceof ClassMetadataInfo && class_exists(Embedded::class) && $metadata->embeddedClasses) {
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
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        if (null === $metadata = $this->getMetadata($class)) {
            return null;
        }

        if ($metadata->hasAssociation($property)) {
            $class = $metadata->getAssociationTargetClass($property);

            if ($metadata->isSingleValuedAssociation($property)) {
                if ($metadata instanceof ClassMetadataInfo) {
                    $associationMapping = $metadata->getAssociationMapping($property);

                    $nullable = $this->isAssociationNullable($associationMapping);
                } else {
                    $nullable = false;
                }

                return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $class)];
            }

            $collectionKeyType = Type::BUILTIN_TYPE_INT;

            if ($metadata instanceof ClassMetadataInfo) {
                $associationMapping = $metadata->getAssociationMapping($property);

                if (isset($associationMapping['indexBy'])) {
                    /** @var ClassMetadataInfo $subMetadata */
                    $subMetadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);

                    // Check if indexBy value is a property
                    $fieldName = $associationMapping['indexBy'];
                    if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                        $fieldName = $subMetadata->getFieldForColumn($associationMapping['indexBy']);
                        // Not a property, maybe a column name?
                        if (null === ($typeOfField = $subMetadata->getTypeOfField($fieldName))) {
                            // Maybe the column name is the association join column?
                            $associationMapping = $subMetadata->getAssociationMapping($fieldName);

                            /** @var ClassMetadataInfo $subMetadata */
                            $indexProperty = $subMetadata->getSingleAssociationReferencedJoinColumnName($fieldName);
                            $subMetadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);

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

        if ($metadata instanceof ClassMetadataInfo && class_exists(Embedded::class) && isset($metadata->embeddedClasses[$property])) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $metadata->embeddedClasses[$property]['class'])];
        }

        if ($metadata->hasField($property)) {
            $typeOfField = $metadata->getTypeOfField($property);

            if (!$builtinType = $this->getPhpType($typeOfField)) {
                return null;
            }

            $nullable = $metadata instanceof ClassMetadataInfo && $metadata->isNullable($property);
            $enumType = null;
            if (null !== $enumClass = $metadata->getFieldMapping($property)['enumType'] ?? null) {
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
                        case Types::ARRAY:
                        case 'json_array':
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
    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
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
     * @see https://github.com/doctrine/doctrine2/blob/v2.5.4/lib/Doctrine/ORM/Tools/EntityGenerator.php#L1221-L1246
     */
    private function isAssociationNullable(array $associationMapping): bool
    {
        if (isset($associationMapping['id']) && $associationMapping['id']) {
            return false;
        }

        if (!isset($associationMapping['joinColumns'])) {
            return true;
        }

        $joinColumns = $associationMapping['joinColumns'];
        foreach ($joinColumns as $joinColumn) {
            if (isset($joinColumn['nullable']) && !$joinColumn['nullable']) {
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
        return match ($doctrineType) {
            Types::SMALLINT,
            Types::INTEGER => Type::BUILTIN_TYPE_INT,
            Types::FLOAT => Type::BUILTIN_TYPE_FLOAT,
            Types::BIGINT,
            Types::STRING,
            Types::TEXT,
            Types::GUID,
            Types::DECIMAL => Type::BUILTIN_TYPE_STRING,
            Types::BOOLEAN => Type::BUILTIN_TYPE_BOOL,
            Types::BLOB,
            Types::BINARY => Type::BUILTIN_TYPE_RESOURCE,
            Types::OBJECT,
            Types::DATE_MUTABLE,
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            'vardatetime',
            Types::TIME_MUTABLE,
            Types::DATE_IMMUTABLE,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
            Types::TIME_IMMUTABLE,
            Types::DATEINTERVAL => Type::BUILTIN_TYPE_OBJECT,
            Types::ARRAY,
            Types::SIMPLE_ARRAY,
            'json_array' => Type::BUILTIN_TYPE_ARRAY,
            default => null,
        };
    }
}
