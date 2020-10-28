<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException as LegacyMappingException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesser implements FormTypeGuesserInterface
{
    protected $registry;

    private $cache = [];

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function guessType(string $class, string $property)
    {
        if (!$ret = $this->getMetadata($class)) {
            return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::LOW_CONFIDENCE);
        }

        [$metadata, $name] = $ret;

        if ($metadata->hasAssociation($property)) {
            $multiple = $metadata->isCollectionValuedAssociation($property);
            $mapping = $metadata->getAssociationMapping($property);

            return new TypeGuess('Symfony\Bridge\Doctrine\Form\Type\EntityType', ['em' => $name, 'class' => $mapping['targetEntity'], 'multiple' => $multiple], Guess::HIGH_CONFIDENCE);
        }

        switch ($metadata->getTypeOfField($property)) {
            case Types::ARRAY:
            case Types::SIMPLE_ARRAY:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CollectionType', [], Guess::MEDIUM_CONFIDENCE);
            case Types::BOOLEAN:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CheckboxType', [], Guess::HIGH_CONFIDENCE);
            case Types::DATETIME_MUTABLE:
            case Types::DATETIMETZ_MUTABLE:
            case 'vardatetime':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', [], Guess::HIGH_CONFIDENCE);
            case Types::DATE_IMMUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE);
            case Types::DATEINTERVAL:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateIntervalType', [], Guess::HIGH_CONFIDENCE);
            case Types::DATE_MUTABLE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', [], Guess::HIGH_CONFIDENCE);
            case Types::DATE_IMMUTABLE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE);
            case Types::TIME_MUTABLE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', [], Guess::HIGH_CONFIDENCE);
            case Types::TIME_IMMUTABLE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE);
            case Types::DECIMAL:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', ['input' => 'string'], Guess::MEDIUM_CONFIDENCE);
            case Types::FLOAT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', [], Guess::MEDIUM_CONFIDENCE);
            case Types::INTEGER:
            case Types::BIGINT:
            case Types::SMALLINT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\IntegerType', [], Guess::MEDIUM_CONFIDENCE);
            case Types::STRING:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::MEDIUM_CONFIDENCE);
            case Types::TEXT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextareaType', [], Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessRequired(string $class, string $property)
    {
        $classMetadatas = $this->getMetadata($class);

        if (!$classMetadatas) {
            return null;
        }

        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $classMetadatas[0];

        // Check whether the field exists and is nullable or not
        if (isset($classMetadata->fieldMappings[$property])) {
            if (!$classMetadata->isNullable($property) && Types::BOOLEAN !== $classMetadata->getTypeOfField($property)) {
                return new ValueGuess(true, Guess::HIGH_CONFIDENCE);
            }

            return new ValueGuess(false, Guess::MEDIUM_CONFIDENCE);
        }

        // Check whether the association exists, is a to-one association and its
        // join column is nullable or not
        if ($classMetadata->isAssociationWithSingleJoinColumn($property)) {
            $mapping = $classMetadata->getAssociationMapping($property);

            if (!isset($mapping['joinColumns'][0]['nullable'])) {
                // The "nullable" option defaults to true, in that case the
                // field should not be required.
                return new ValueGuess(false, Guess::HIGH_CONFIDENCE);
            }

            return new ValueGuess(!$mapping['joinColumns'][0]['nullable'], Guess::HIGH_CONFIDENCE);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function guessMaxLength(string $class, string $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && isset($ret[0]->fieldMappings[$property]) && !$ret[0]->hasAssociation($property)) {
            $mapping = $ret[0]->getFieldMapping($property);

            if (isset($mapping['length'])) {
                return new ValueGuess($mapping['length'], Guess::HIGH_CONFIDENCE);
            }

            if (\in_array($ret[0]->getTypeOfField($property), [Types::DECIMAL, Types::FLOAT])) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function guessPattern(string $class, string $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && isset($ret[0]->fieldMappings[$property]) && !$ret[0]->hasAssociation($property)) {
            if (\in_array($ret[0]->getTypeOfField($property), [Types::DECIMAL, Types::FLOAT])) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    protected function getMetadata(string $class)
    {
        // normalize class name
        $class = self::getRealClass(ltrim($class, '\\'));

        if (\array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }

        $this->cache[$class] = null;
        foreach ($this->registry->getManagers() as $name => $em) {
            try {
                return $this->cache[$class] = [$em->getClassMetadata($class), $name];
            } catch (MappingException $e) {
                // not an entity or mapped super class
            } catch (LegacyMappingException $e) {
                // not an entity or mapped super class, using Doctrine ORM 2.2
            }
        }

        return null;
    }

    private static function getRealClass(string $class): string
    {
        if (false === $pos = strrpos($class, '\\'.Proxy::MARKER.'\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }
}
