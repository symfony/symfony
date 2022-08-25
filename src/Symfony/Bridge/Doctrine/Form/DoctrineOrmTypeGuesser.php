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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesser implements FormTypeGuesserInterface
{
    protected $registry;

    private array $cache = [];

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        if (!$ret = $this->getMetadata($class)) {
            return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);
        }

        [$metadata, $name] = $ret;

        if ($metadata->hasAssociation($property)) {
            $multiple = $metadata->isCollectionValuedAssociation($property);
            $mapping = $metadata->getAssociationMapping($property);

            return new TypeGuess(EntityType::class, ['em' => $name, 'class' => $mapping['targetEntity'], 'multiple' => $multiple], Guess::HIGH_CONFIDENCE);
        }

        return match ($metadata->getTypeOfField($property)) {
            Types::ARRAY,
            Types::SIMPLE_ARRAY => new TypeGuess(CollectionType::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::BOOLEAN => new TypeGuess(CheckboxType::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            'vardatetime' => new TypeGuess(DateTimeType::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE => new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::DATEINTERVAL => new TypeGuess(DateIntervalType::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATE_MUTABLE => new TypeGuess(DateType::class, [], Guess::HIGH_CONFIDENCE),
            Types::DATE_IMMUTABLE => new TypeGuess(DateType::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::TIME_MUTABLE => new TypeGuess(TimeType::class, [], Guess::HIGH_CONFIDENCE),
            Types::TIME_IMMUTABLE => new TypeGuess(TimeType::class, ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE),
            Types::DECIMAL => new TypeGuess(NumberType::class, ['input' => 'string'], Guess::MEDIUM_CONFIDENCE),
            Types::FLOAT => new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::INTEGER,
            Types::BIGINT,
            Types::SMALLINT => new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::STRING => new TypeGuess(TextType::class, [], Guess::MEDIUM_CONFIDENCE),
            Types::TEXT => new TypeGuess(TextareaType::class, [], Guess::MEDIUM_CONFIDENCE),
            default => new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE),
        };
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
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

    public function guessMaxLength(string $class, string $property): ?ValueGuess
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

    public function guessPattern(string $class, string $property): ?ValueGuess
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
            } catch (MappingException) {
                // not an entity or mapped super class
            } catch (LegacyMappingException) {
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
