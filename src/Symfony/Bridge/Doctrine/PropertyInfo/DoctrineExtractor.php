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

use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException as OrmMappingException;
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
    private $classMetadataFactory;

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

        if ($metadata instanceof ClassMetadataInfo && class_exists('Doctrine\ORM\Mapping\Embedded') && $metadata->embeddedClasses) {
            $properties = array_filter($properties, function ($property) {
                return false === strpos($property, '.');
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
                    $indexProperty = $associationMapping['indexBy'];
                    /** @var ClassMetadataInfo $subMetadata */
                    $subMetadata = $this->entityManager ? $this->entityManager->getClassMetadata($associationMapping['targetEntity']) : $this->classMetadataFactory->getMetadataFor($associationMapping['targetEntity']);
                    $typeOfField = $subMetadata->getTypeOfField($indexProperty);

                    if (null === $typeOfField) {
                        $associationMapping = $subMetadata->getAssociationMapping($indexProperty);

                        /** @var ClassMetadataInfo $subMetadata */
                        $indexProperty = $subMetadata->getSingleAssociationReferencedJoinColumnName($indexProperty);
                        $subMetadata = $this->entityManager ? $this->entityManager->getClassMetadata($associationMapping['targetEntity']) : $this->classMetadataFactory->getMetadataFor($associationMapping['targetEntity']);
                        $typeOfField = $subMetadata->getTypeOfField($indexProperty);
                    }

                    $collectionKeyType = $this->getPhpType($typeOfField);
                }
            }

            return [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type($collectionKeyType),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, $class)
            )];
        }

        if ($metadata instanceof ClassMetadataInfo && class_exists('Doctrine\ORM\Mapping\Embedded') && isset($metadata->embeddedClasses[$property])) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $metadata->embeddedClasses[$property]['class'])];
        }

        if ($metadata->hasField($property)) {
            $typeOfField = $metadata->getTypeOfField($property);
            $nullable = $metadata instanceof ClassMetadataInfo && $metadata->isNullable($property);

            switch ($typeOfField) {
                case DBALType::DATE:
                case DBALType::DATETIME:
                case DBALType::DATETIMETZ:
                case 'vardatetime':
                case DBALType::TIME:
                    return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateTime')];

                case 'date_immutable':
                case 'datetime_immutable':
                case 'datetimetz_immutable':
                case 'time_immutable':
                    return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateTimeImmutable')];

                case 'dateinterval':
                    return [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateInterval')];

                case DBALType::TARRAY:
                    return [new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true)];

                case DBALType::SIMPLE_ARRAY:
                    return [new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))];

                case DBALType::JSON_ARRAY:
                    return [new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true)];

                default:
                    $builtinType = $this->getPhpType($typeOfField);

                    return $builtinType ? [new Type($builtinType, $nullable)] : null;
            }
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
            return $this->entityManager ? $this->entityManager->getClassMetadata($class) : $this->classMetadataFactory->getMetadataFor($class);
        } catch (MappingException | OrmMappingException $exception) {
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
            case DBALType::SMALLINT:
            case DBALType::INTEGER:
                return Type::BUILTIN_TYPE_INT;

            case DBALType::FLOAT:
                return Type::BUILTIN_TYPE_FLOAT;

            case DBALType::BIGINT:
            case DBALType::STRING:
            case DBALType::TEXT:
            case DBALType::GUID:
            case DBALType::DECIMAL:
                return Type::BUILTIN_TYPE_STRING;

            case DBALType::BOOLEAN:
                return Type::BUILTIN_TYPE_BOOL;

            case DBALType::BLOB:
            case 'binary':
                return Type::BUILTIN_TYPE_RESOURCE;

            case DBALType::OBJECT:
                return Type::BUILTIN_TYPE_OBJECT;
        }

        return null;
    }
}
