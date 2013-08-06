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
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\Mapping\MappingException as LegacyMappingException;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesser implements FormTypeGuesserInterface
{
    protected $registry;

    private $cache;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $this->cache = array();
    }

    /**
     * {@inheritDoc}
     */
    public function guessType($class, $property)
    {
        if (!$ret = $this->getMetadata($class)) {
            return new TypeGuess('text', array(), Guess::LOW_CONFIDENCE);
        }

        list($metadata, $name) = $ret;

        if ($metadata->hasAssociation($property)) {
            $multiple = $metadata->isCollectionValuedAssociation($property);
            $mapping = $metadata->getAssociationMapping($property);

            return new TypeGuess('entity', array('em' => $name, 'class' => $mapping['targetEntity'], 'multiple' => $multiple), Guess::HIGH_CONFIDENCE);
        }

        switch ($metadata->getTypeOfField($property)) {
            case 'array':
                return new TypeGuess('collection', array(), Guess::MEDIUM_CONFIDENCE);
            case 'boolean':
                return new TypeGuess('checkbox', array(), Guess::HIGH_CONFIDENCE);
            case 'datetime':
            case 'vardatetime':
            case 'datetimetz':
                return new TypeGuess('datetime', array(), Guess::HIGH_CONFIDENCE);
            case 'date':
                return new TypeGuess('date', array(), Guess::HIGH_CONFIDENCE);
            case 'time':
                return new TypeGuess('time', array(), Guess::HIGH_CONFIDENCE);
            case 'decimal':
            case 'float':
                return new TypeGuess('number', array(), Guess::MEDIUM_CONFIDENCE);
            case 'integer':
            case 'bigint':
            case 'smallint':
                return new TypeGuess('integer', array(), Guess::MEDIUM_CONFIDENCE);
            case 'string':
                return new TypeGuess('text', array(), Guess::MEDIUM_CONFIDENCE);
            case 'text':
                return new TypeGuess('textarea', array(), Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess('text', array(), Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function guessRequired($class, $property)
    {
        $classMetadatas = $this->getMetadata($class);

        if (!$classMetadatas) {
            return null;
        }

        /* @var ClassMetadataInfo $classMetadata */
        $classMetadata = $classMetadatas[0];

        // Check whether the field exists and is nullable or not
        if ($classMetadata->hasField($property)) {
            if (!$classMetadata->isNullable($property)) {
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
     * {@inheritDoc}
     */
    public function guessMaxLength($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && $ret[0]->hasField($property) && !$ret[0]->hasAssociation($property)) {
            $mapping = $ret[0]->getFieldMapping($property);

            if (isset($mapping['length'])) {
                return new ValueGuess($mapping['length'], Guess::HIGH_CONFIDENCE);
            }

            if (in_array($ret[0]->getTypeOfField($property), array('decimal', 'float'))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function guessPattern($class, $property)
    {
        $ret = $this->getMetadata($class);
        if ($ret && $ret[0]->hasField($property) && !$ret[0]->hasAssociation($property)) {
            if (in_array($ret[0]->getTypeOfField($property), array('decimal', 'float'))) {
                return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }
    }

    protected function getMetadata($class)
    {
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
