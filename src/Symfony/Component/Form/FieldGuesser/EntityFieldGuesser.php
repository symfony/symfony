<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldGuesser;

use Doctrine\ORM\EntityManager;

/**
 * Guesses form fields from the metadata of Doctrine 2
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class EntityFieldGuesser implements FieldGuesserInterface
{
    /**
     * The Doctrine 2 entity manager
     * @var Doctrine\ORM\EntityManager
     */
    protected $em = null;

    /**
     * Constructor
     *
     * @param ClassMetadataFactoryInterface $metadataFactory
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Returns whether Doctrine 2 metadata exists for that class
     *
     * @return boolean
     */
    protected function isMappedClass($class)
    {
        return !$this->em->getConfiguration()->getMetadataDriverImpl()->isTransient($class);
    }

    /**
     * @inheritDoc
     */
    public function guessType($class, $property)
    {
        if ($this->isMappedClass($class)) {
            $metadata = $this->em->getClassMetadata($class);

            if ($metadata->hasAssociation($property)) {
                $multiple = $metadata->isCollectionValuedAssociation($property);
                $mapping = $metadata->getAssociationMapping($property);

                return new FieldTypeGuess(
                    'entity',
                    array(
                        'em' => $this->em,
                        'class' => $mapping['targetEntity'],
                        'multiple' => $multiple,
                    ),
                    FieldGuess::HIGH_CONFIDENCE
                );
            } else {
                switch ($metadata->getTypeOfField($property))
                {
        //            case 'array':
        //                return new FieldTypeGuess(
        //                    'Collection',
        //                    array(),
        //                    FieldGuess::HIGH_CONFIDENCE
        //                );
                    case 'boolean':
                        return new FieldTypeGuess(
                            'checkbox',
                            array(),
                            FieldGuess::HIGH_CONFIDENCE
                        );
                    case 'datetime':
                    case 'vardatetime':
                    case 'datetimetz':
                        return new FieldTypeGuess(
                            'datetime',
                            array(),
                            FieldGuess::HIGH_CONFIDENCE
                        );
                    case 'date':
                        return new FieldTypeGuess(
                            'date',
                            array(),
                            FieldGuess::HIGH_CONFIDENCE
                        );
                    case 'decimal':
                    case 'float':
                        return new FieldTypeGuess(
                            'number',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'bigint':
                    case 'smallint':
                        return new FieldTypeGuess(
                            'integer',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new FieldTypeGuess(
                            'text',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'text':
                        return new FieldTypeGuess(
                            'textarea',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'time':
                        return new FieldTypeGuess(
                            'time',
                            array(),
                            FieldGuess::HIGH_CONFIDENCE
                        );
    //                case 'object': ???
                }
            }
        }

        return new FieldTypeGuess(
            'text',
            array(),
            FieldGuess::LOW_CONFIDENCE
        );
    }

    /**
     * @inheritDoc
     */
    public function guessRequired($class, $property)
    {
        if ($this->isMappedClass($class)) {
            $metadata = $this->em->getClassMetadata($class);

            if ($metadata->hasField($property)) {
                if (!$metadata->isNullable($property)) {
                    return new FieldGuess(
                        true,
                        FieldGuess::HIGH_CONFIDENCE
                    );
                }

                return new FieldGuess(
                    false,
                    FieldGuess::MEDIUM_CONFIDENCE
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function guessMaxLength($class, $property)
    {
        if ($this->isMappedClass($class)) {
            $metadata = $this->em->getClassMetadata($class);

            if (!$metadata->hasAssociation($property)) {
                $mapping = $metadata->getFieldMapping($property);


                if (isset($mapping['length'])) {
                    return new FieldGuess(
                        $mapping['length'],
                        FieldGuess::HIGH_CONFIDENCE
                    );
                }
            }
        }
    }
}