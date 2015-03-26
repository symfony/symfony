<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Form\Exception\RuntimeException;

/**
 * A utility for reading object IDs.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal This class is meant for internal use only.
 */
class IdReader
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @var bool
     */
    private $singleId;

    /**
     * @var bool
     */
    private $intId;

    /**
     * @var string
     */
    private $idField;

    public function __construct(ObjectManager $om, ClassMetadata $classMetadata)
    {
        $ids = $classMetadata->getIdentifierFieldNames();
        $idType = $classMetadata->getTypeOfField(current($ids));

        $this->om = $om;
        $this->classMetadata = $classMetadata;
        $this->singleId = 1 === count($ids);
        $this->intId = $this->singleId && 1 === count($ids) && in_array($idType, array('integer', 'smallint', 'bigint'));
        $this->idField = current($ids);
    }

    /**
     * Returns whether the class has a single-column ID.
     *
     * @return bool Returns `true` if the class has a single-column ID and
     *              `false` otherwise.
     */
    public function isSingleId()
    {
        return $this->singleId;
    }

    /**
     * Returns whether the class has a single-column integer ID.
     *
     * @return bool Returns `true` if the class has a single-column integer ID
     *              and `false` otherwise.
     */
    public function isIntId()
    {
        return $this->intId;
    }

    /**
     * Returns the ID value for an object.
     *
     * This method assumes that the object has a single-column ID.
     *
     * @param object $object The object.
     *
     * @return mixed The ID value.
     */
    public function getIdValue($object)
    {
        if (!$object) {
            return null;
        }

        if (!$this->om->contains($object)) {
            throw new RuntimeException(
                'Entities passed to the choice field must be managed. Maybe '.
                'persist them in the entity manager?'
            );
        }

        $this->om->initializeObject($object);

        return current($this->classMetadata->getIdentifierValues($object));
    }

    /**
     * Returns the name of the ID field.
     *
     * This method assumes that the object has a single-column ID.
     *
     * @return string The name of the ID field.
     */
    public function getIdField()
    {
        return $this->idField;
    }
}
