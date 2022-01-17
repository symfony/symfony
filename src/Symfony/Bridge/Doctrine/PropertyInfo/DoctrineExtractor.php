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
            return $this->getAssociationType($metadata, $property);
        }

        if ($metadata->hasClass($property)) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $metadata->embeddedClasses[$property]['class'])];
        }

        if ($metadata->hasField($property)) {
            $this->getFieldType($metadata, $property);
        }

        return null;
    }

    private function getFieldType(?ClassMetadata $metadata, string $property): ?array
    {
        $typeOfField = $metadata->getTypeOfField($property);

        if (!$builtinType = $this->getPhpType($typeOfField)) {
            return null;
        }

        $nullable = $metadata instanceof ClassMetadataInfo && $metadata->isNullable($property);

        switch ($builtinType) {
            case Type::BUILTIN_TYPE_OBJECT:
                switch ($typeOfField) {
                    case Types::DATE_MUTABLE:
                    case Types::DATETIME_MUTABLE:
                    case Types::DATETIMETZ_MUTABLE:
                    case 'vardatetime':
                    case Types::TIME_MUTABLE:
                        return [Type::dateTime($nullable)];

                    case Types::DATE_IMMUTABLE:
                    case Types::DATETIME_IMMUTABLE:
                    case Types::DATETIMETZ_IMMUTABLE:
                    case Types::TIME_IMMUTABLE:
                        return [Type::dateTimeImmutable($nullable)];

                    case Types::DATEINTERVAL:
                        return [Type::dateInterval($nullable)];
                }

                break;
            case Type::BUILTIN_TYPE_ARRAY:
                switch ($typeOfField) {
                    case Types::ARRAY:
                    case 'json_array':
                    case 'json':
                        return [Type::array($nullable)];

                    case Types::SIMPLE_ARRAY:
                        return [Type::array($nullable, true)];
                }
        }

        return [new Type($builtinType, $nullable)];
    }

    private function getAssociationType(?ClassMetadata $metadata, string $property): ?array
    {
        $class = $metadata->getAssociationTargetClass($property);
        $associationMapping = $metadata->getAssociationMapping($property);

        if ($metadata->isSingleValuedAssociation($property)) {
            $nullable = $metadata instanceof ClassMetadataInfo ? $this->isAssociationNullable($associationMapping) : false;

            return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $class)];
        }

        $collectionKeyType = Type::BUILTIN_TYPE_INT;
        if ($metadata instanceof ClassMetadataInfo && isset($associationMapping['indexBy'])) {
            $typeOfField = $this->checkTypeOfField($associationMapping);

            if (!($collectionKeyType = $this->getPhpType($typeOfField))) {
                return null;
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

    private function checkTypeOfField(array $associationMapping): ?string
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);

        $fieldName = $associationMapping['indexBy'];
        if (!(null === ($typeOfField = $metadata->getTypeOfField($fieldName)))) {
            return $typeOfField;
        }

        $fieldName = $metadata->getFieldForColumn($associationMapping['indexBy']);
        //Not a property, maybe a column name?
        if (!(null === ($typeOfField = $metadata->getTypeOfField($fieldName)))) {
            return $typeOfField;
        }

        //Maybe the column name is the association join column?
        $associationMapping = $metadata->getAssociationMapping($fieldName);

        /** @var ClassMetadataInfo $metadata */
        $metadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);
        $indexProperty = $metadata->getSingleAssociationReferencedJoinColumnName($fieldName);

        //Not a property, maybe a column name?
        if (!(null === ($typeOfField = $metadata->getTypeOfField($indexProperty)))) {
            return $typeOfField;
        }

        $fieldName = $metadata->getFieldForColumn($indexProperty);
        return $metadata->getTypeOfField($fieldName);
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
        } catch (MappingException|OrmMappingException $exception) {
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

            case Types::OBJECT:
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

            case Types::ARRAY:
            case Types::SIMPLE_ARRAY:
            case 'json_array':
                return Type::BUILTIN_TYPE_ARRAY;
        }

        return null;
    }
}
