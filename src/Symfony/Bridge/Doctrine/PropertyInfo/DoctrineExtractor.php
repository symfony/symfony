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

use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException as OrmMappingException;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Extracts data using Doctrine ORM and ODM metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface
{
    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;

    public function __construct(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        try {
            $metadata = $this->classMetadataFactory->getMetadataFor($class);
        } catch (MappingException $exception) {
            return;
        } catch (OrmMappingException $exception) {
            return;
        }

        return array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        try {
            $metadata = $this->classMetadataFactory->getMetadataFor($class);
        } catch (MappingException $exception) {
            return;
        } catch (OrmMappingException $exception) {
            return;
        }

        if ($metadata->hasAssociation($property)) {
            $class = $metadata->getAssociationTargetClass($property);

            if ($metadata->isSingleValuedAssociation($property)) {
                if ($metadata instanceof ClassMetadataInfo) {
                    $nullable = isset($metadata->discriminatorColumn['nullable']) ? $metadata->discriminatorColumn['nullable'] : false;
                } else {
                    $nullable = false;
                }

                return array(new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $class));
            }

            return array(new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_INT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, $class)
            ));
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
                    return array(new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, 'DateTime'));

                case DBALType::TARRAY:
                    return array(new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true));

                case DBALType::SIMPLE_ARRAY:
                    return array(new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)));

                case DBALType::JSON_ARRAY:
                    return array(new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true));

                default:
                    $builtinType = $this->getPhpType($typeOfField);

                    return $builtinType ? array(new Type($builtinType, $nullable)) : null;
            }
        }
    }

    /**
     * Gets the corresponding built-in PHP type.
     *
     * @param string $doctrineType
     *
     * @return string|null
     */
    private function getPhpType($doctrineType)
    {
        switch ($doctrineType) {
            case DBALType::SMALLINT:
            case DBALType::BIGINT:
            case DBALType::INTEGER:
                return Type::BUILTIN_TYPE_INT;

            case DBALType::FLOAT:
            case DBALType::DECIMAL:
                return Type::BUILTIN_TYPE_FLOAT;

            case DBALType::STRING:
            case DBALType::TEXT:
            case DBALType::GUID:
                return Type::BUILTIN_TYPE_STRING;

            case DBALType::BOOLEAN:
                return Type::BUILTIN_TYPE_BOOL;

            case DBALType::BLOB:
            case 'binary':
                return Type::BUILTIN_TYPE_RESOURCE;

            case DBALType::OBJECT:
                return Type::BUILTIN_TYPE_OBJECT;

            default:
                return;
        }
    }
}
