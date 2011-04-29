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

use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Doctrine\ORM\EntityManager;

class DoctrineOrmTypeGuesser implements FormTypeGuesserInterface
{
    /**
     * The Doctrine 2 entity manager
     * @var Doctrine\ORM\EntityManager
     */
    protected $em = null;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Returns whether Doctrine 2 metadata exists for that class
     *
     * @return Boolean
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

                return new TypeGuess(
                    'entity',
                    array(
                        'em' => $this->em,
                        'class' => $mapping['targetEntity'],
                        'multiple' => $multiple,
                    ),
                    Guess::HIGH_CONFIDENCE
                );
            } else {
                switch ($metadata->getTypeOfField($property))
                {
        //            case 'array':
        //                return new TypeGuess(
        //                    'Collection',
        //                    array(),
        //                    Guess::HIGH_CONFIDENCE
        //                );
                    case 'boolean':
                        return new TypeGuess(
                            'checkbox',
                            array(),
                            Guess::HIGH_CONFIDENCE
                        );
                    case 'datetime':
                    case 'vardatetime':
                    case 'datetimetz':
                        return new TypeGuess(
                            'datetime',
                            array(),
                            Guess::HIGH_CONFIDENCE
                        );
                    case 'date':
                        return new TypeGuess(
                            'date',
                            array(),
                            Guess::HIGH_CONFIDENCE
                        );
                    case 'decimal':
                    case 'float':
                        return new TypeGuess(
                            'number',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'bigint':
                    case 'smallint':
                        return new TypeGuess(
                            'integer',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new TypeGuess(
                            'text',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'text':
                        return new TypeGuess(
                            'textarea',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'time':
                        return new TypeGuess(
                            'time',
                            array(),
                            Guess::HIGH_CONFIDENCE
                        );
    //                case 'object': ???
                }
            }
        }

        return new TypeGuess(
            'text',
            array(),
            Guess::LOW_CONFIDENCE
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
                    return new ValueGuess(
                        true,
                        Guess::HIGH_CONFIDENCE
                    );
                }

                return new ValueGuess(
                    false,
                    Guess::MEDIUM_CONFIDENCE
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
                    return new ValueGuess(
                        $mapping['length'],
                        Guess::HIGH_CONFIDENCE
                    );
                }
            }
        }
    }
}
