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

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException as LegacyMappingException;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Doctrine\Common\Util\ClassUtils;

class DoctrineOrmTypeGuesser implements FormTypeGuesserInterface
{
    protected $registry;

    private $cache = array();

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function guessType($class, $property)
    {
        if (!$ret = $this->getMetadata($class)) {
            return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::LOW_CONFIDENCE);
        }

        list($metadata, $name) = $ret;

        if ($metadata->hasAssociation($property)) {
            $multiple = $metadata->isCollectionValuedAssociation($property);
            $mapping = $metadata->getAssociationMapping($property);

            return new TypeGuess('Symfony\Bridge\Doctrine\Form\Type\EntityType', array('em' => $name, 'class' => $mapping['targetEntity'], 'multiple' => $multiple), Guess::HIGH_CONFIDENCE);
        }

        switch ($metadata->getTypeOfField($property)) {
            case Type::TARRAY:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::BOOLEAN:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CheckboxType', array(), Guess::HIGH_CONFIDENCE);
            case Type::DATETIME:
            case Type::DATETIMETZ:
            case 'vardatetime':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', array(), Guess::HIGH_CONFIDENCE);
            case 'dateinterval':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateIntervalType', array(), Guess::HIGH_CONFIDENCE);
            case Type::DATE:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', array(), Guess::HIGH_CONFIDENCE);
            case Type::TIME:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', array(), Guess::HIGH_CONFIDENCE);
            case Type::DECIMAL:
            case Type::FLOAT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::INTEGER:
            case Type::BIGINT:
            case Type::SMALLINT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\IntegerType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::STRING:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::MEDIUM_CONFIDENCE);
            case Type::TEXT:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextareaType', array(), Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', array(), Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessRequired($class, $property)
    {
        $classMetadatas = $this->getMetadata($class);

        if (!$classMetadatas) {
            return;
        }

        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $classMetadatas[0];

        // Check whether the field exists and is nullable or not
        if (isset($classMetadata->fieldMappings[$property])) {
            if (!$classMetadata->isNullable($property) && Type::BOOLEAN !== $classMetadata->getTypeOfField($property)) {
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
    }

    /**
     * {@inheritdoc}
     */
    public function guessMaxLength($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && isset($ret[0]->fieldMappings[$property]) && !$ret[0]->hasAssociation($property)) {
            $mapping = $ret[0]->getFieldMapping($property);

            if (isset($mapping['length'])) {
                return new ValueGuess($mapping['length'], Guess::HIGH_CONFIDENCE);
            }

            if (in_array($ret[0]->getTypeOfField($property), array(Type::DECIMAL, Type::FLOAT))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function guessPattern($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && isset($ret[0]->fieldMappings[$property]) && !$ret[0]->hasAssociation($property)) {
            if (in_array($ret[0]->getTypeOfField($property), array(Type::DECIMAL, Type::FLOAT))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    protected function getMetadata($class)
    {
        // normalize class name
        $class = ClassUtils::getRealClass(ltrim($class, '\\'));

        if (array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }

        $this->cache[$class] = null;
        foreach ($this->registry->getManagers() as $name => $em) {
            try {
                return $this->cache[$class] = array($em->getClassMetadata($class), $name);
            } catch (MappingException $e) {
                // not an entity or mapped super class
            } catch (LegacyMappingException $e) {
                // not an entity or mapped super class, using Doctrine ORM 2.2
            }
        }
    }
}
