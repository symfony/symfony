<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

use Doctrine\ORM\EntityManager;

/**
 * Guesses form fields from the metadata of Doctrine 2
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class EntityFieldFactoryGuesser implements FieldFactoryGuesserInterface
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
    public function guessClass($class, $property)
    {
        if ($this->isMappedClass($class)) {
            $metadata = $this->em->getClassMetadata($class);

            if ($metadata->hasAssociation($property)) {
                $multiple = $metadata->isCollectionValuedAssociation($property);
                $mapping = $metadata->getAssociationMapping($property);

                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\EntityChoiceField',
                    array(
                        'em' => $this->em,
                        'class' => $mapping['targetEntity'],
                        'multiple' => $multiple,
                    ),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            } else {
                switch ($metadata->getTypeOfField($property))
                {
        //            case 'array':
        //                return new FieldFactoryClassGuess(
        //                    'Symfony\Component\Form\CollectionField',
        //                    array(),
        //                    FieldFactoryGuess::HIGH_CONFIDENCE
        //                );
                    case 'boolean':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\CheckboxField',
                            array(),
                            FieldFactoryGuess::HIGH_CONFIDENCE
                        );
                    case 'datetime':
                    case 'vardatetime':
                    case 'datetimetz':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\DateTimeField',
                            array(),
                            FieldFactoryGuess::HIGH_CONFIDENCE
                        );
                    case 'date':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\DateField',
                            array(),
                            FieldFactoryGuess::HIGH_CONFIDENCE
                        );
                    case 'decimal':
                    case 'float':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\NumberField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'bigint':
                    case 'smallint':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\IntegerField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\TextField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'text':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\TextareaField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'time':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\TimeField',
                            array(),
                            FieldFactoryGuess::HIGH_CONFIDENCE
                        );
    //                case 'object': ???
                }
            }
        }

        return new FieldFactoryClassGuess(
            'Symfony\Component\Form\TextField',
            array(),
            FieldFactoryGuess::LOW_CONFIDENCE
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
                    return new FieldFactoryGuess(
                        true,
                        FieldFactoryGuess::HIGH_CONFIDENCE
                    );
                }

                return new FieldFactoryGuess(
                    false,
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
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
                    return new FieldFactoryGuess(
                        $mapping['length'],
                        FieldFactoryGuess::HIGH_CONFIDENCE
                    );
                }
            }
        }
    }
}